<?php

namespace Tests\Unit\Pricing;

use App\Guesstimate\Offers\BudgetPricing;
use App\Guesstimate\Pricing\BudgetEngine;
use App\Guesstimate\Pricing\BudgetInput;
use App\Guesstimate\Pricing\Cost;
use App\Guesstimate\Pricing\LineItem;
use App\Guesstimate\Pricing\PricingException;
use App\Guesstimate\Pricing\Rounding;
use PHPUnit\Framework\TestCase;

/**
 * Model budżetowy (silnik 2.x): cena = godziny × stawka, warsztaty w cenie, podział reszty 20/30/50.
 */
class BudgetEngineTest extends TestCase
{
	private const RATES = ['low' => 15000, 'mid' => 18000, 'high' => 20000];

	private function item(string $group, string $min, string $max, string $label = 'Pozycja'): LineItem
	{
		return new LineItem(LineItem::SOURCE_WORK, $label, $group, $min, $max);
	}

	private function calc(array $items, array $costs = [], int $workshops = 200000, array $split = ['ux' => '20', 'ui' => '30', 'dev' => '50'], Rounding $rounding = new Rounding, array $rates = self::RATES): array
	{
		return (new BudgetEngine)->calculate(new BudgetInput($rates, $items, $costs, $workshops, $split, $rounding));
	}

	/**
	 * Snapshot jak z calc.xlsx: 8 podstron po 4–6 h (UI 2–3 + Dev 2–3), zoptymalizowany UX 16 h.
	 */
	private function excelSnapshot(int $typeUnits = 0, string $typeLabel = 'Strona internetowa'): array
	{
		$nodes = [];

		for ($i = 1; $i <= 8; $i++) {
			$nodes[] = ['name' => "Podstrona {$i}"];
		}

		return [
			'calc_model' => 'budget',
			'rates' => self::RATES,
			'ux' => ['label' => 'Zoptymalizowany UX', 'min' => '16', 'max' => '16'],
			'rounding' => ['hours_step' => '0', 'price_step' => 1],
			'budget' => [
				'project_type' => 'website',
				'project_type_label' => $typeLabel,
				'type_units' => (string) $typeUnits,
				'page_hours' => ['ui_min' => '2', 'ui_max' => '3', 'dev_min' => '2', 'dev_max' => '3'],
				'workshops' => 200000,
				'split' => ['ux' => '20', 'ui' => '30', 'dev' => '50'],
				'part_step' => 1,
			],
			'nodes' => $nodes,
			'features' => [],
			'work_items' => [],
			'costs' => [],
		];
	}

	public function test_specification_example_twenty_thousand(): void
	{
		// 100 h × 200 zł = 20 000 zł (High); warsztaty 2 000 zł są częścią ceny.
		$result = $this->calc([$this->item('work', '100', '100')]);
		$high = $result['levels']['high'];

		$this->assertSame(['min' => 2000000, 'max' => 2000000], $high['total']);
		$this->assertSame(200000, $high['workshops']);
		$this->assertSame(360000, $high['parts']['ux']['min']);
		$this->assertSame(540000, $high['parts']['ui']['min']);
		$this->assertSame(900000, $high['parts']['dev']['min']);
	}

	public function test_parts_and_workshops_always_sum_to_the_price(): void
	{
		$result = $this->calc([$this->item('pages', '37.25', '61.75'), $this->item('feature', '3.5', '9')], rounding: new Rounding('0.25', 1, 1000));

		foreach ($result['levels'] as $level) {
			foreach (['min', 'max'] as $b) {
				$sum = $level['workshops'] + $level['parts']['ux'][$b] + $level['parts']['ui'][$b] + $level['parts']['dev'][$b];
				$this->assertSame($level['total'][$b], $sum);
			}
		}
	}

	public function test_price_equals_hours_times_rate_for_each_level(): void
	{
		$result = $this->calc([$this->item('pages', '40', '60')]);

		$this->assertSame(['min' => 600000, 'max' => 900000], $result['levels']['low']['total']);
		$this->assertSame(['min' => 720000, 'max' => 1080000], $result['levels']['mid']['total']);
		$this->assertSame(['min' => 800000, 'max' => 1200000], $result['levels']['high']['total']);
		$this->assertSame(['min' => 600000, 'max' => 1200000], $result['range']);
	}

	public function test_hours_are_identical_in_every_level(): void
	{
		$result = $this->calc([$this->item('pages', '40', '60')]);

		foreach ($result['levels'] as $level) {
			$this->assertSame(['min' => '40.00', 'max' => '60.00'], $level['hours']);
		}
	}

	public function test_excel_example_hours_and_prices(): void
	{
		$result = (new BudgetEngine)->calculate(BudgetPricing::toInput($this->excelSnapshot()));

		$this->assertSame(['min' => '48.00', 'max' => '64.00'], $result['hours']);
		$this->assertSame(['min' => 960000, 'max' => 1280000], $result['levels']['high']['total']);
		// Excel E49:F51 — (9 600 − 2 000) × 20/30/50%.
		$this->assertSame(['min' => 152000, 'max' => 216000], $result['levels']['high']['parts']['ux']);
		$this->assertSame(['min' => 228000, 'max' => 324000], $result['levels']['high']['parts']['ui']);
		$this->assertSame(['min' => 380000, 'max' => 540000], $result['levels']['high']['parts']['dev']);
	}

	public function test_project_type_units_are_a_separate_line(): void
	{
		// Sklep internetowy: +8 jednostek (Excel D22), liczone tymi samymi 4–6 h.
		$result = (new BudgetEngine)->calculate(BudgetPricing::toInput($this->excelSnapshot(8, 'Sklep internetowy')));
		$groups = array_column($result['groups'], null, 'key');

		$this->assertSame(['min' => '32.00', 'max' => '48.00'], $groups['pages']['hours']);
		$this->assertSame(['min' => '32.00', 'max' => '48.00'], $groups['type']['hours']);
		$this->assertSame(1, $groups['type']['count']);
		$this->assertSame(['min' => '80.00', 'max' => '112.00'], $result['hours']);
	}

	public function test_sitemap_stats_do_not_mix_pages_and_units(): void
	{
		$stats = BudgetPricing::stats($this->excelSnapshot(5));

		$this->assertSame(8, $stats['pages']);
		$this->assertSame('5', $stats['type_units']);
		$this->assertSame('13', $stats['units']);
		$this->assertSame(['min' => '26.00', 'max' => '39.00'], $stats['ui']);
		$this->assertSame(['min' => '26.00', 'max' => '39.00'], $stats['dev']);
	}

	public function test_page_override_replaces_default_hours(): void
	{
		$snapshot = $this->excelSnapshot();
		$snapshot['nodes'] = [['name' => 'Strona główna', 'hours_ui_min' => '6', 'hours_ui_max' => '8', 'hours_dev_min' => null, 'hours_dev_max' => '']];
		$snapshot['ux'] = null;

		$result = (new BudgetEngine)->calculate(BudgetPricing::toInput(array_merge($snapshot, ['budget' => array_merge($snapshot['budget'], ['workshops' => 0])])));

		$this->assertSame(['min' => '8.00', 'max' => '11.00'], $result['hours']);
	}

	public function test_ux_hours_are_counted_once(): void
	{
		$result = $this->calc([$this->item('ux', '16', '16'), $this->item('pages', '32', '48')]);

		$this->assertSame(['min' => '48.00', 'max' => '64.00'], $result['hours']);
		// Kwota UX to udział w budżecie, a nie dodatkowe godziny UX × stawka.
		$high = $result['levels']['high'];
		$this->assertSame($high['total']['min'], $high['workshops'] + $high['parts']['ux']['min'] + $high['parts']['ui']['min'] + $high['parts']['dev']['min']);
	}

	public function test_features_custom_features_and_work_add_hours(): void
	{
		$result = $this->calc([
			$this->item('pages', '20', '30'),
			$this->item('feature', '6', '10', 'Polylang'),
			$this->item('feature', '4', '8', 'Własna'),
			$this->item('work', '2', '4', 'Szkolenie'),
		]);
		$groups = array_column($result['groups'], null, 'key');

		$this->assertSame(['min' => '32.00', 'max' => '52.00'], $result['hours']);
		$this->assertSame(2, $groups['feature']['count']);
		$this->assertCount(4, $result['lines']);
	}

	public function test_external_costs_are_outside_the_price(): void
	{
		$result = $this->calc([$this->item('pages', '40', '40')], [
			new Cost('ACF Pro', 50000),
			new Cost('Hosting', 3000, '1', Cost::MONTHLY),
			new Cost('Domena', 10000, '2', Cost::YEARLY),
		]);

		$this->assertSame(['min' => 800000, 'max' => 800000], $result['levels']['high']['total']);
		$this->assertSame(50000, $result['costs']['one_time']);
		$this->assertSame(3000, $result['costs']['monthly']);
		$this->assertSame(20000, $result['costs']['yearly']);
		$this->assertSame(56000, $result['costs']['annual_recurring']);
	}

	public function test_price_below_workshops_is_an_error(): void
	{
		$this->expectException(PricingException::class);
		$this->expectExceptionMessageMatches('/warsztatów/');

		$this->calc([$this->item('pages', '5', '10')]);
	}

	public function test_zero_workshops_splits_the_whole_price(): void
	{
		$result = $this->calc([$this->item('pages', '10', '10')], workshops: 0);

		$this->assertSame(30000, $result['levels']['low']['parts']['ux']['min']);
		$this->assertSame(45000, $result['levels']['low']['parts']['ui']['min']);
		$this->assertSame(75000, $result['levels']['low']['parts']['dev']['min']);
	}

	public function test_split_must_sum_to_one_hundred(): void
	{
		$this->expectException(PricingException::class);
		$this->expectExceptionMessageMatches('/100%/');

		$this->calc([$this->item('pages', '40', '40')], split: ['ux' => '20', 'ui' => '30', 'dev' => '40']);
	}

	public function test_custom_split(): void
	{
		$result = $this->calc([$this->item('pages', '100', '100')], split: ['ux' => '10', 'ui' => '20', 'dev' => '70']);
		$parts = $result['levels']['high']['parts'];

		$this->assertSame(180000, $parts['ux']['min']);
		$this->assertSame(360000, $parts['ui']['min']);
		$this->assertSame(1260000, $parts['dev']['min']);
	}

	public function test_part_rounding_to_ten_zloty_keeps_the_sum(): void
	{
		// 37 h × 180 zł = 6 660 zł; reszta 4 660 zł → UX 932 → 930, UI 1 398 → 1 400, Dev 2 330.
		$result = $this->calc([$this->item('pages', '37', '37')], rounding: new Rounding('0', 1, 1000));
		$mid = $result['levels']['mid'];

		$this->assertSame(93000, $mid['parts']['ux']['min']);
		$this->assertSame(140000, $mid['parts']['ui']['min']);
		$this->assertSame(233000, $mid['parts']['dev']['min']);
		$this->assertSame(666000, $mid['total']['min']);
	}

	public function test_price_rounding_step(): void
	{
		$result = $this->calc([$this->item('pages', '33.33', '33.33')], rounding: new Rounding('0', 100));

		$this->assertSame(499950, $result['levels']['low']['raw']['min']);
		$this->assertSame(500000, $result['levels']['low']['total']['min']);
		$this->assertSame(50, $result['levels']['low']['rounding']['min']);
	}

	public function test_hours_step_rounds_total_hours(): void
	{
		$result = $this->calc([$this->item('pages', '20.1', '30.2'), $this->item('work', '20.0', '30.0')], rounding: new Rounding('0.25'));

		$this->assertSame(['min' => '40.00', 'max' => '60.25'], $result['hours']);
	}

	public function test_rates_must_keep_order(): void
	{
		$this->expectException(PricingException::class);

		$this->calc([$this->item('pages', '40', '40')], rates: ['low' => 20000, 'mid' => 18000, 'high' => 25000]);
	}

	public function test_rates_must_be_positive(): void
	{
		$this->expectException(PricingException::class);

		$this->calc([$this->item('pages', '40', '40')], rates: ['low' => 0, 'mid' => 18000, 'high' => 20000]);
	}

	public function test_min_above_max_is_an_error(): void
	{
		$this->expectException(PricingException::class);

		$this->calc([$this->item('pages', '50', '40')]);
	}

	public function test_unknown_group_is_an_error(): void
	{
		$this->expectException(PricingException::class);

		$this->calc([$this->item('dev', '50', '60')]);
	}

	public function test_result_is_deterministic(): void
	{
		$items = [$this->item('pages', '40', '60'), $this->item('ux', '32', '32')];

		$this->assertSame($this->calc($items), $this->calc($items));
		$this->assertSame('2.0.0', $this->calc($items)['engine_version']);
	}
}
