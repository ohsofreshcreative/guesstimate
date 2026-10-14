<?php

namespace App\Guesstimate\Offers;

use App\Guesstimate\Pricing\BudgetInput;
use App\Guesstimate\Pricing\Cost;
use App\Guesstimate\Pricing\Decimal;
use App\Guesstimate\Pricing\LineItem;
use App\Guesstimate\Pricing\Rounding;
use Brick\Math\BigDecimal;

/**
 * Zamienia snapshot wersji w modelu budżetowym (silnik 2.x) na BudgetInput.
 *
 * Reguły domenowe:
 * - każda podstrona z mapy = 1 jednostka; godziny UI i Dev z ustawień oferty albo nadpisane dla podstrony,
 * - rodzaj projektu dodaje jednostki kalkulacyjne (nie adresy URL) liczone tymi samymi godzinami na jednostkę,
 *   jako osobna pozycja — nie są wliczane do mapy strony,
 * - zakres UX to jedna pozycja godzinowa (liczona raz),
 * - koszty zewnętrzne nie wchodzą do ceny.
 */
final class BudgetPricing
{
	public const MODEL = 'budget';

	public static function toInput(array $snapshot): BudgetInput
	{
		$budget = $snapshot['budget'] ?? [];

		return new BudgetInput(
			rates: array_map('intval', $snapshot['rates']),
			items: self::items($snapshot),
			costs: array_map(fn (array $cost) => new Cost(
				name: $cost['name'],
				amount: (int) $cost['amount'],
				quantity: Decimal::format(Decimal::of((string) $cost['quantity'])),
				billing: $cost['billing'],
			), $snapshot['costs'] ?? []),
			workshops: (int) ($budget['workshops'] ?? 0),
			split: array_map('strval', $budget['split'] ?? ['ux' => '20', 'ui' => '30', 'dev' => '50']),
			rounding: new Rounding(
				hoursStep: (string) ($snapshot['rounding']['hours_step'] ?? '0'),
				priceStep: (int) ($snapshot['rounding']['price_step'] ?? 1),
				partStep: (int) ($budget['part_step'] ?? 1),
			),
		);
	}

	/**
	 * @return list<LineItem>
	 */
	public static function items(array $snapshot): array
	{
		$items = [];
		$budget = $snapshot['budget'] ?? [];

		if (! empty($snapshot['ux'])) {
			$items[] = new LineItem(LineItem::SOURCE_UX, (string) $snapshot['ux']['label'], 'ux', self::h($snapshot['ux']['min']), self::h($snapshot['ux']['max']));
		}

		foreach (self::pages($snapshot) as $page) {
			$items[] = new LineItem(
				LineItem::SOURCE_TEMPLATE,
				$page['name'],
				'pages',
				Decimal::format($page['ui_min']->plus($page['dev_min'])),
				Decimal::format($page['ui_max']->plus($page['dev_max'])),
			);
		}

		$units = self::units($budget);

		if ($units->isPositive()) {
			$per = self::pageHours($budget);
			$items[] = new LineItem(
				LineItem::SOURCE_PAGE_EXTRA,
				sprintf('%s: %s jedn. kalkulacyjnych', $budget['project_type_label'] ?? 'Rodzaj projektu', self::n($units)),
				'type',
				Decimal::format($units->multipliedBy($per['ui_min']->plus($per['dev_min']))),
				Decimal::format($units->multipliedBy($per['ui_max']->plus($per['dev_max']))),
			);
		}

		foreach ($snapshot['features'] ?? [] as $feature) {
			$items[] = new LineItem(LineItem::SOURCE_FEATURE, $feature['name'], 'feature', self::h($feature['hours_min']), self::h($feature['hours_max']));
		}

		foreach ($snapshot['work_items'] ?? [] as $work) {
			$items[] = new LineItem(LineItem::SOURCE_WORK, $work['name'], 'work', self::h($work['hours_min']), self::h($work['hours_max']));
		}

		return $items;
	}

	/**
	 * Podsumowanie mapy strony: podstrony ręczne, jednostki rodzaju projektu, razem i szacunek godzin UI / Dev.
	 */
	public static function stats(array $snapshot): array
	{
		$budget = $snapshot['budget'] ?? [];
		$per = self::pageHours($budget);
		$units = self::units($budget);
		$ui = ['min' => $units->multipliedBy($per['ui_min']), 'max' => $units->multipliedBy($per['ui_max'])];
		$dev = ['min' => $units->multipliedBy($per['dev_min']), 'max' => $units->multipliedBy($per['dev_max'])];
		$pages = self::pages($snapshot);

		foreach ($pages as $page) {
			foreach (['min', 'max'] as $b) {
				$ui[$b] = $ui[$b]->plus($page['ui_' . $b]);
				$dev[$b] = $dev[$b]->plus($page['dev_' . $b]);
			}
		}

		return [
			'pages' => count($pages),
			'type_units' => self::n($units),
			'units' => self::n($units->plus(count($pages))),
			'ui' => ['min' => Decimal::format($ui['min']), 'max' => Decimal::format($ui['max'])],
			'dev' => ['min' => Decimal::format($dev['min']), 'max' => Decimal::format($dev['max'])],
		];
	}

	/**
	 * @return list<array{name: string, ui_min: BigDecimal, ui_max: BigDecimal, dev_min: BigDecimal, dev_max: BigDecimal}>
	 */
	private static function pages(array $snapshot): array
	{
		$per = self::pageHours($snapshot['budget'] ?? []);
		$pages = [];

		foreach ($snapshot['nodes'] ?? [] as $node) {
			$page = ['name' => (string) $node['name']];

			foreach (['ui_min', 'ui_max', 'dev_min', 'dev_max'] as $field) {
				$value = $node['hours_' . $field] ?? null;
				$page[$field] = ($value === null || $value === '') ? $per[$field] : Decimal::of((string) $value);
			}

			$pages[] = $page;
		}

		return $pages;
	}

	/**
	 * @return array{ui_min: BigDecimal, ui_max: BigDecimal, dev_min: BigDecimal, dev_max: BigDecimal}
	 */
	private static function pageHours(array $budget): array
	{
		$hours = $budget['page_hours'] ?? [];

		return [
			'ui_min' => Decimal::of(self::h($hours['ui_min'] ?? 0)),
			'ui_max' => Decimal::of(self::h($hours['ui_max'] ?? 0)),
			'dev_min' => Decimal::of(self::h($hours['dev_min'] ?? 0)),
			'dev_max' => Decimal::of(self::h($hours['dev_max'] ?? 0)),
		];
	}

	private static function units(array $budget): BigDecimal
	{
		return Decimal::of(self::h($budget['type_units'] ?? 0));
	}

	private static function h(mixed $value): string
	{
		return Decimal::format(Decimal::of(($value === null || $value === '') ? '0' : (string) $value));
	}

	/**
	 * Liczba bez zbędnych zer: 8.00 → '8', 2.50 → '2.5'.
	 */
	private static function n(BigDecimal $value): string
	{
		return rtrim(rtrim(Decimal::format($value), '0'), '.');
	}
}
