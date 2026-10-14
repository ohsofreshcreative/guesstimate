<?php

namespace App\Guesstimate\Pricing;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Silnik wyceny. Czysta funkcja: ten sam PricingInput daje zawsze ten sam wynik.
 *
 * Zasady:
 * - trzy warianty ryzyka różnią się wyłącznie stawką godzinową; godziny są wspólne,
 * - widełki [min, max] godzin dają kwotę od–do w każdym wariancie,
 * - każdy etap ma dokładnie jedno źródło wartości (kwota stała / procent / godziny),
 * - koszty jednorazowe wchodzą do ceny, cykliczne są raportowane osobno,
 * - kwoty w groszach (int), godziny jako BigDecimal, zaokrąglanie HALF_UP w ustalonych miejscach.
 */
final class PricingEngine
{
	public const VERSION = '1.0.0';

	public const MAX_RATE = Rates::MAX;

	public function calculate(PricingInput $input): array
	{
		$this->assertRates($input);

		$stages = $this->indexStages($input->stages);
		$itemsByStage = $this->groupItems($input->items, $stages);
		$step = Decimal::of($input->rounding->hoursStep);
		$levels = RiskLevel::cases();
		$warnings = [];

		$rows = [];
		$poolKeys = [];
		$fixedInPool = 0;

		foreach ($stages as $key => $stage) {
			$items = $itemsByStage[$key] ?? [];
			$source = $stage->source($input->model);

			$row = [
				'key' => $key,
				'name' => $stage->name,
				'enabled' => $stage->enabled,
				'source' => $source,
				'percent' => $source === Stage::SOURCE_PERCENT ? Decimal::format(Decimal::of($stage->percent)) : null,
				'item_count' => count($items),
				'hours' => null,
				'amounts' => $this->zeroAmounts(),
			];

			if (! $stage->enabled) {
				if ($items) {
					$warnings[] = sprintf('Etap „%s” jest wyłączony — %d poz. nie wchodzi do wyceny.', $stage->name, count($items));
				}

				$rows[$key] = $row;

				continue;
			}

			if ($source === Stage::SOURCE_FIXED) {
				if ($items) {
					$warnings[] = sprintf('Etap „%s” ma kwotę stałą — godziny jego %d poz. nie są doliczane.', $stage->name, count($items));
				}

				foreach ($levels as $level) {
					$row['amounts'][$level->value] = ['min' => $stage->fixedAmount, 'max' => $stage->fixedAmount];
				}

				$fixedInPool += $stage->fixedAmount;
			} elseif ($source === Stage::SOURCE_HOURS) {
				[$min, $max] = $this->sumHours($items, $step);
				$row['hours'] = ['min' => Decimal::format($min), 'max' => Decimal::format($max)];

				foreach ($levels as $level) {
					$rate = $input->rate($level);
					$row['amounts'][$level->value] = ['min' => Decimal::money($min, $rate), 'max' => Decimal::money($max, $rate)];
				}
			} else {
				$poolKeys[] = $key;
			}

			$rows[$key] = $row;
		}

		$poolHours = null;

		if ($poolKeys) {
			$poolHours = $this->distributePool($input, $stages, $itemsByStage, $poolKeys, $fixedInPool, $step, $rows);
		}

		return $this->summarize($input, $rows, $poolHours, $warnings);
	}

	/**
	 * Model procentowy (calc.xlsx): kwota puli = godziny puli × stawka; kwoty stałe są w niej zawarte,
	 * a reszta jest dzielona procentami. Ostatni etap puli przejmuje resztę z zaokrągleń (suma zawsze się zgadza).
	 *
	 * @return array{min: string, max: string}
	 */
	private function distributePool(PricingInput $input, array $stages, array $itemsByStage, array $poolKeys, int $fixed, BigDecimal $step, array &$rows): array
	{
		$percentSum = BigDecimal::zero();
		$poolItems = [];

		foreach ($poolKeys as $key) {
			$percentSum = $percentSum->plus(Decimal::of($stages[$key]->percent));
			$poolItems = array_merge($poolItems, $itemsByStage[$key] ?? []);
		}

		if (! $percentSum->isEqualTo(100)) {
			throw new PricingException(sprintf(
				'Udziały procentowe włączonych etapów muszą sumować się do 100%% (teraz: %s%%).',
				Decimal::format($percentSum)
			));
		}

		[$min, $max] = $this->sumHours($poolItems, $step);
		$lastKey = end($poolKeys);

		foreach (RiskLevel::cases() as $level) {
			$rate = $input->rate($level);

			foreach (['min' => $min, 'max' => $max] as $bound => $hours) {
				$remainder = Decimal::money($hours, $rate) - $fixed;

				if ($remainder < 0) {
					throw new PricingException(sprintf(
						'Kwota projektu w wariancie %s (%s) jest niższa niż kwoty stałe etapów. Dodaj godziny albo zmniejsz kwotę stałą.',
						$level->label(),
						$bound === 'min' ? 'od' : 'do'
					));
				}

				$assigned = 0;

				foreach ($poolKeys as $key) {
					$share = $key === $lastKey
						? $remainder - $assigned
						: BigDecimal::of($remainder)->multipliedBy($stages[$key]->percent)->dividedBy(100, 0, RoundingMode::HALF_UP)->toInt();

					$assigned += $share;
					$rows[$key]['amounts'][$level->value][$bound] = $share;
				}
			}
		}

		foreach ($poolKeys as $key) {
			$percent = Decimal::of($stages[$key]->percent)->dividedBy(100, 4);
			$rows[$key]['hours'] = [
				'min' => Decimal::format($min->multipliedBy($percent)),
				'max' => Decimal::format($max->multipliedBy($percent)),
			];
		}

		return ['min' => Decimal::format($min), 'max' => Decimal::format($max)];
	}

	private function summarize(PricingInput $input, array $rows, ?array $poolHours, array $warnings): array
	{
		$hoursMin = BigDecimal::zero();
		$hoursMax = BigDecimal::zero();

		foreach ($rows as $row) {
			if ($row['enabled'] && $row['source'] === Stage::SOURCE_HOURS) {
				$hoursMin = $hoursMin->plus($row['hours']['min']);
				$hoursMax = $hoursMax->plus($row['hours']['max']);
			}
		}

		if ($poolHours) {
			$hoursMin = $hoursMin->plus($poolHours['min']);
			$hoursMax = $hoursMax->plus($poolHours['max']);
		}

		$costs = $this->sumCosts($input->costs);
		$levels = [];

		foreach (RiskLevel::cases() as $level) {
			$labor = ['min' => 0, 'max' => 0];

			foreach ($rows as $row) {
				$labor['min'] += $row['amounts'][$level->value]['min'];
				$labor['max'] += $row['amounts'][$level->value]['max'];
			}

			$entry = [
				'label' => $level->label(),
				'rate' => $input->rate($level),
				'hours' => ['min' => Decimal::format($hoursMin), 'max' => Decimal::format($hoursMax)],
				'labor' => $labor,
			];

			foreach (['min', 'max'] as $bound) {
				$raw = $labor[$bound] + $costs['one_time'];
				$total = Decimal::roundMoney($raw, $input->rounding->priceStep);

				$entry['raw'][$bound] = $raw;
				$entry['rounding'][$bound] = $total - $raw;
				$entry['total'][$bound] = $total;
			}

			$levels[$level->value] = $entry;
		}

		return [
			'engine_version' => self::VERSION,
			'model' => $input->model->value,
			'rates' => [
				'low' => $input->rate(RiskLevel::Low),
				'mid' => $input->rate(RiskLevel::Mid),
				'high' => $input->rate(RiskLevel::High),
			],
			'stages' => array_values($rows),
			'hours' => ['min' => Decimal::format($hoursMin), 'max' => Decimal::format($hoursMax)],
			'pool_hours' => $poolHours,
			'costs' => $costs,
			'levels' => $levels,
			'range' => [
				'min' => $levels[RiskLevel::Low->value]['total']['min'],
				'max' => $levels[RiskLevel::High->value]['total']['max'],
			],
			'warnings' => $warnings,
		];
	}

	/**
	 * @param  list<Cost>  $costs
	 */
	private function sumCosts(array $costs): array
	{
		return CostTotals::of($costs);
	}

	/**
	 * @param  list<LineItem>  $items
	 * @return array{0: BigDecimal, 1: BigDecimal}
	 */
	private function sumHours(array $items, BigDecimal $step): array
	{
		$min = BigDecimal::zero();
		$max = BigDecimal::zero();

		foreach ($items as $item) {
			$min = $min->plus($item->min);
			$max = $max->plus($item->max);
		}

		return [Decimal::roundToStep($min, $step), Decimal::roundToStep($max, $step)];
	}

	/**
	 * @param  list<Stage>  $stages
	 * @return array<string, Stage>
	 */
	private function indexStages(array $stages): array
	{
		$indexed = [];

		foreach ($stages as $stage) {
			if (isset($indexed[$stage->key])) {
				throw new PricingException(sprintf('Etap „%s” występuje dwa razy.', $stage->name));
			}

			if ($stage->fixedAmount !== null && $stage->fixedAmount < 0) {
				throw new PricingException(sprintf('Kwota stała etapu „%s” nie może być ujemna.', $stage->name));
			}

			$indexed[$stage->key] = $stage;
		}

		return $indexed;
	}

	/**
	 * @param  list<LineItem>  $items
	 * @param  array<string, Stage>  $stages
	 * @return array<string, list<LineItem>>
	 */
	private function groupItems(array $items, array $stages): array
	{
		$grouped = [];

		foreach ($items as $item) {
			if (! isset($stages[$item->stageKey])) {
				throw new PricingException(sprintf('Pozycja „%s” ma nieznany etap.', $item->label));
			}

			if (! self::isHours($item->min) || ! self::isHours($item->max)) {
				throw new PricingException(sprintf('Pozycja „%s” ma nieprawidłową liczbę godzin.', $item->label));
			}

			if (Decimal::of($item->min)->isGreaterThan($item->max)) {
				throw new PricingException(sprintf('Pozycja „%s”: minimum godzin jest większe niż maksimum.', $item->label));
			}

			$grouped[$item->stageKey][] = $item;
		}

		return $grouped;
	}

	private function assertRates(PricingInput $input): void
	{
		Rates::assert($input->rates);
	}

	private function zeroAmounts(): array
	{
		$amounts = [];

		foreach (RiskLevel::cases() as $level) {
			$amounts[$level->value] = ['min' => 0, 'max' => 0];
		}

		return $amounts;
	}

	public static function isHours(string $value): bool
	{
		return (bool) preg_match('/^\d{1,6}(\.\d{1,2})?$/', $value);
	}
}
