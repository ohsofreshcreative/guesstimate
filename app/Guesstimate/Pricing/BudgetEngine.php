<?php

namespace App\Guesstimate\Pricing;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Silnik wyceny 2.x (model budżetowy). Czysta funkcja, bez WordPressa i bazy.
 *
 * Zasady:
 * - cena wariantu = suma godzin × stawka wariantu (Low / Mid / High), widełki min–max,
 * - warsztaty Discovery są kwotą stałą ZAWARTĄ w cenie (nie są doliczane),
 * - reszta ceny jest dzielona procentowo na UX / UI / Development — to prezentacja budżetu,
 *   nie dodatkowe naliczenie: suma części zawsze równa się cenie,
 * - godziny UX są jedną z pozycji godzinowych i liczą się raz,
 * - koszty zewnętrzne (jednorazowe i cykliczne) są poza ceną i raportowane osobno.
 */
final class BudgetEngine
{
	public const VERSION = '2.0.0';

	public const GROUPS = [
		'ux' => 'Zakres UX',
		'pages' => 'Mapa strony',
		'type' => 'Rodzaj projektu',
		'feature' => 'Funkcjonalności',
		'work' => 'Prace dodatkowe',
	];

	public const PARTS = ['ux' => 'UX Design', 'ui' => 'UI Design', 'dev' => 'Development i wdrożenie'];

	public function calculate(BudgetInput $input): array
	{
		Rates::assert($input->rates);
		$split = $this->split($input->split);

		if ($input->workshops < 0) {
			throw new PricingException('Kwota warsztatów nie może być ujemna.');
		}

		[$groups, $lines] = $this->groups($input->items);
		$min = BigDecimal::zero();
		$max = BigDecimal::zero();

		foreach ($groups as $group) {
			$min = $min->plus($group['hours']['min']);
			$max = $max->plus($group['hours']['max']);
		}

		$step = Decimal::of($input->rounding->hoursStep);
		$hours = ['min' => Decimal::roundToStep($min, $step), 'max' => Decimal::roundToStep($max, $step)];
		$levels = [];

		foreach (RiskLevel::cases() as $level) {
			$rate = $input->rates[$level->value];
			$entry = [
				'label' => $level->label(),
				'rate' => $rate,
				'hours' => ['min' => Decimal::format($hours['min']), 'max' => Decimal::format($hours['max'])],
				'workshops' => $input->workshops,
			];

			foreach (['min', 'max'] as $bound) {
				$raw = Decimal::money($hours[$bound], $rate);
				$total = Decimal::roundMoney($raw, $input->rounding->priceStep);

				if ($total < $input->workshops) {
					throw new PricingException(sprintf(
						'Cena w wariancie %s (%s) jest niższa niż kwota warsztatów Discovery. Dodaj godziny albo zmniejsz kwotę warsztatów.',
						$level->label(),
						$bound === 'min' ? 'od' : 'do'
					));
				}

				$entry['raw'][$bound] = $raw;
				$entry['rounding'][$bound] = $total - $raw;
				$entry['total'][$bound] = $total;

				foreach ($this->divide($total - $input->workshops, $split, $input->rounding->partStep) as $part => $amount) {
					$entry['parts'][$part][$bound] = $amount;
				}
			}

			$levels[$level->value] = $entry;
		}

		return [
			'engine_version' => self::VERSION,
			'model' => 'budget',
			'rates' => $input->rates,
			'groups' => array_values($groups),
			'lines' => $lines,
			'hours' => $levels['low']['hours'],
			'workshops' => $input->workshops,
			'split' => array_map(fn (BigDecimal $p) => Decimal::format($p), $split),
			'costs' => CostTotals::of($input->costs),
			'levels' => $levels,
			'range' => ['min' => $levels['low']['total']['min'], 'max' => $levels['high']['total']['max']],
			'warnings' => [],
		];
	}

	/**
	 * Podział kwoty na części. UX i UI zaokrąglane do kroku, Development przejmuje resztę,
	 * więc suma części jest dokładnie równa kwocie.
	 *
	 * @param  array<string, BigDecimal>  $split
	 * @return array<string, int>
	 */
	private function divide(int $amount, array $split, int $step): array
	{
		$parts = [];

		foreach (['ux', 'ui'] as $key) {
			$share = BigDecimal::of($amount)->multipliedBy($split[$key])->dividedBy(100, 0, RoundingMode::HALF_UP)->toInt();
			$parts[$key] = Decimal::roundMoney($share, $step);
		}

		$parts['dev'] = $amount - $parts['ux'] - $parts['ui'];

		// Zaokrąglenie w górę UX i UI nie może zabrać Developmentowi więcej, niż ma.
		if ($parts['dev'] < 0) {
			$parts['ui'] += $parts['dev'];
			$parts['dev'] = 0;
		}

		return $parts;
	}

	/**
	 * @return array<string, BigDecimal>
	 */
	private function split(array $split): array
	{
		$values = [];
		$sum = BigDecimal::zero();

		foreach (array_keys(self::PARTS) as $key) {
			$raw = (string) ($split[$key] ?? '');

			if (! preg_match('/^\d{1,3}(\.\d{1,2})?$/', $raw)) {
				throw new PricingException(sprintf('Udział „%s” musi być liczbą od 0 do 100.', self::PARTS[$key]));
			}

			$values[$key] = Decimal::of($raw);
			$sum = $sum->plus($values[$key]);
		}

		if (! $sum->isEqualTo(100)) {
			throw new PricingException(sprintf('Udziały UX, UI i Development muszą sumować się do 100%% (teraz: %s%%).', Decimal::format($sum)));
		}

		if (! $values['dev']->isPositive()) {
			throw new PricingException('Udział Development musi być większy od zera.');
		}

		return $values;
	}

	/**
	 * @param  list<LineItem>  $items
	 * @return array{0: array<string, array>, 1: list<array>}
	 */
	private function groups(array $items): array
	{
		$groups = [];
		$lines = [];

		foreach ($items as $item) {
			if (! isset(self::GROUPS[$item->stageKey])) {
				throw new PricingException(sprintf('Pozycja „%s” ma nieznaną grupę.', $item->label));
			}

			if (! PricingEngine::isHours($item->min) || ! PricingEngine::isHours($item->max)) {
				throw new PricingException(sprintf('Pozycja „%s” ma nieprawidłową liczbę godzin.', $item->label));
			}

			if (Decimal::of($item->min)->isGreaterThan($item->max)) {
				throw new PricingException(sprintf('Pozycja „%s”: minimum godzin jest większe niż maksimum.', $item->label));
			}

			$key = $item->stageKey;
			$groups[$key] ??= ['key' => $key, 'label' => self::GROUPS[$key], 'count' => 0, 'hours' => ['min' => '0.00', 'max' => '0.00']];
			$groups[$key]['count']++;
			$groups[$key]['hours']['min'] = Decimal::format(Decimal::of($groups[$key]['hours']['min'])->plus($item->min));
			$groups[$key]['hours']['max'] = Decimal::format(Decimal::of($groups[$key]['hours']['max'])->plus($item->max));
			$lines[] = ['group' => $key, 'label' => $item->label, 'hours' => ['min' => Decimal::format(Decimal::of($item->min)), 'max' => Decimal::format(Decimal::of($item->max))]];
		}

		// Stała kolejność grup niezależnie od kolejności pozycji.
		$ordered = [];

		foreach (array_keys(self::GROUPS) as $key) {
			if (isset($groups[$key])) {
				$ordered[$key] = $groups[$key];
			}
		}

		return [$ordered, $lines];
	}
}
