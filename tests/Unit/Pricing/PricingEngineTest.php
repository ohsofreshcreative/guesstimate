<?php

namespace Tests\Unit\Pricing;

use App\Guesstimate\Pricing\CalcModel;
use App\Guesstimate\Pricing\Cost;
use App\Guesstimate\Pricing\PricingException;
use App\Guesstimate\Pricing\Rounding;
use PHPUnit\Framework\Attributes\DataProvider;

class PricingEngineTest extends PricingTestCase
{
	public function test_risk_levels_differ_only_by_rate_and_share_the_same_hours(): void
	{
		$result = $this->calculate([$this->item('dev', '48', '64')], stageOverrides: ['discovery' => ['fixed' => null]]);

		foreach (['low', 'mid', 'high'] as $level) {
			$this->assertSame(['min' => '48.00', 'max' => '64.00'], $result['levels'][$level]['hours']);
		}

		$this->assertSame(['min' => 720000, 'max' => 960000], $this->totals($result, 'low'));
		$this->assertSame(['min' => 864000, 'max' => 1152000], $this->totals($result, 'mid'));
		$this->assertSame(['min' => 960000, 'max' => 1280000], $this->totals($result, 'high'));
	}

	public function test_raising_high_rate_does_not_change_hours_or_other_levels(): void
	{
		$items = [$this->item('dev', '10', '20')];
		$base = $this->calculate($items, stageOverrides: ['discovery' => ['fixed' => null]]);
		$raised = $this->calculate($items, stageOverrides: ['discovery' => ['fixed' => null]], rates: ['low' => 15000, 'mid' => 18000, 'high' => 25000]);

		$this->assertSame($base['hours'], $raised['hours']);
		$this->assertSame($base['levels']['low'], $raised['levels']['low']);
		$this->assertSame($base['levels']['mid'], $raised['levels']['mid']);
		$this->assertSame(['min' => 250000, 'max' => 500000], $this->totals($raised, 'high'));
	}

	public function test_hourly_model_sums_hours_per_stage(): void
	{
		$result = $this->calculate([
			$this->item('ux', '16', '16'),
			$this->item('ui', '10', '15'),
			$this->item('dev', '20', '30'),
			$this->item('dev', '5', '5'),
		], stageOverrides: ['discovery' => ['fixed' => null]]);

		$this->assertSame(['min' => '25.00', 'max' => '35.00'], $this->stage($result, 'dev')['hours']);
		$this->assertSame(['min' => 500000, 'max' => 700000], $this->stage($result, 'dev')['amounts']['high']);
		$this->assertSame(['min' => '51.00', 'max' => '66.00'], $result['hours']);
	}

	public function test_fixed_stage_is_added_on_top_in_hourly_model_and_its_hours_are_ignored(): void
	{
		$result = $this->calculate([
			$this->item('discovery', '8', '10', 'Warsztat'),
			$this->item('dev', '10', '10'),
		]);

		$this->assertSame(['min' => 200000, 'max' => 200000], $this->stage($result, 'discovery')['amounts']['low']);
		$this->assertSame(['min' => 350000, 'max' => 350000], $this->totals($result, 'low'));
		$this->assertSame(['min' => '10.00', 'max' => '10.00'], $result['hours']);
		$this->assertCount(1, $result['warnings']);
	}

	public function test_disabled_stage_is_excluded_with_a_warning(): void
	{
		$result = $this->calculate([
			$this->item('dev', '10', '10'),
			$this->item('deploy', '4', '6'),
		], stageOverrides: ['discovery' => ['fixed' => null], 'deploy' => ['enabled' => false]]);

		$this->assertSame(['min' => 200000, 'max' => 200000], $this->totals($result, 'high'));
		$this->assertSame(['min' => '10.00', 'max' => '10.00'], $result['hours']);
		$this->assertStringContainsString('Wdrożenie', $result['warnings'][0]);
	}

	public function test_percent_model_does_not_count_ux_and_ui_hours_twice(): void
	{
		$items = [$this->item('ux', '16', '16'), $this->item('ui', '10', '10'), $this->item('dev', '24', '24')];
		$result = $this->calculate($items, CalcModel::Percent, ['discovery' => ['fixed' => null]]);

		// 50 h × 200 zł = 10 000 zł, dzielone 20/30/50 — nie 10 000 zł + osobno UX i UI.
		$this->assertSame(['min' => 1000000, 'max' => 1000000], $this->totals($result, 'high'));
		$this->assertSame(['min' => 200000, 'max' => 200000], $this->stage($result, 'ux')['amounts']['high']);
		$this->assertSame(['min' => '10.00', 'max' => '10.00'], $this->stage($result, 'ux')['hours']);
	}

	public function test_percent_model_adds_hourly_stages_outside_the_pool(): void
	{
		$result = $this->calculate([$this->item('dev', '50', '50'), $this->item('deploy', '5', '5')], CalcModel::Percent);

		// pula: 50 h × 200 = 10 000 zł (w tym Discovery 2 000), wdrożenie: 5 h × 200 = 1 000 zł
		$this->assertSame(['min' => 1100000, 'max' => 1100000], $this->totals($result, 'high'));
		$this->assertSame(['min' => '55.00', 'max' => '55.00'], $result['hours']);
	}

	public function test_percent_shares_always_sum_to_the_pool_amount(): void
	{
		$result = $this->calculate([$this->item('dev', '1.33', '7.77')], CalcModel::Percent, ['discovery' => ['fixed' => 1]], ['low' => 13333, 'mid' => 17777, 'high' => 19999]);

		foreach (['low', 'mid', 'high'] as $level) {
			foreach (['min', 'max'] as $bound) {
				$sum = 0;

				foreach ($result['stages'] as $stage) {
					$sum += $stage['amounts'][$level][$bound];
				}

				$this->assertSame($result['levels'][$level]['labor'][$bound], $sum);
			}
		}

		$this->assertSame(26599, $result['levels']['high']['labor']['min']); // 1,33 h × 199,99 zł = 265,9867 zł → 265,99 zł
	}

	public function test_percent_shares_must_sum_to_100(): void
	{
		$this->expectException(PricingException::class);
		$this->expectExceptionMessage('100%');

		$this->calculate([$this->item('dev', '10', '10')], CalcModel::Percent, ['dev' => ['percent' => '40']]);
	}

	public function test_project_below_fixed_amount_is_rejected_in_percent_model(): void
	{
		$this->expectException(PricingException::class);

		$this->calculate([$this->item('dev', '5', '5')], CalcModel::Percent);
	}

	public function test_hours_and_price_rounding_keep_the_breakdown_consistent(): void
	{
		$result = $this->calculate(
			[$this->item('dev', '10.1', '12.6'), $this->item('ui', '3.3', '3.3')],
			stageOverrides: ['discovery' => ['fixed' => null]],
			rates: ['low' => 15050, 'mid' => 18050, 'high' => 20050],
			rounding: new Rounding('0.25', 10000),
		);

		$this->assertSame(['min' => '10.00', 'max' => '12.50'], $this->stage($result, 'dev')['hours']);
		$this->assertSame(['min' => '3.25', 'max' => '3.25'], $this->stage($result, 'ui')['hours']);

		$low = $result['levels']['low'];
		$this->assertSame(199413, $low['raw']['min']); // 13,25 h × 150,50 zł = 1 994,125 → 1 994,13 zł
		$this->assertSame(200000, $low['total']['min']);
		$this->assertSame($low['total']['min'] - $low['raw']['min'], $low['rounding']['min']);
	}

	public function test_one_time_costs_are_in_the_price_and_recurring_costs_are_separate(): void
	{
		$result = $this->calculate([$this->item('dev', '10', '10')], stageOverrides: ['discovery' => ['fixed' => null]], costs: [
			new Cost('Licencja ACF Pro', 49900, '1', Cost::ONE_TIME),
			new Cost('Wtyczka', 10000, '2', Cost::ONE_TIME),
			new Cost('Hosting', 5000, '1', Cost::MONTHLY),
			new Cost('Domena', 9900, '1', Cost::YEARLY),
		]);

		$this->assertSame(69900, $result['costs']['one_time']);
		$this->assertSame(5000, $result['costs']['monthly']);
		$this->assertSame(9900, $result['costs']['yearly']);
		$this->assertSame(69900, $result['costs']['annual_recurring']);
		$this->assertSame(['min' => 269900, 'max' => 269900], $this->totals($result, 'high'));
	}

	public function test_same_input_always_gives_the_same_result(): void
	{
		$items = [$this->item('dev', '12.35', '17.85'), $this->item('ux', '16', '32')];

		$this->assertSame(
			$this->calculate($items, CalcModel::Percent, rates: ['low' => 15001, 'mid' => 18003, 'high' => 20007]),
			$this->calculate($items, CalcModel::Percent, rates: ['low' => 15001, 'mid' => 18003, 'high' => 20007]),
		);
	}

	#[DataProvider('invalidRates')]
	public function test_invalid_rates_are_rejected(array $rates): void
	{
		$this->expectException(PricingException::class);

		$this->calculate([$this->item('dev', '1', '1')], rates: $rates);
	}

	public static function invalidRates(): array
	{
		return [
			'zero' => [['low' => 0, 'mid' => 18000, 'high' => 20000]],
			'ujemna' => [['low' => -100, 'mid' => 18000, 'high' => 20000]],
			'low > mid' => [['low' => 19000, 'mid' => 18000, 'high' => 20000]],
			'mid > high' => [['low' => 15000, 'mid' => 21000, 'high' => 20000]],
			'ponad limit techniczny' => [['low' => 15000, 'mid' => 18000, 'high' => 10_000_001]],
			'brak stawki' => [['low' => 15000, 'mid' => 18000]],
		];
	}

	public function test_equal_rates_are_allowed(): void
	{
		$result = $this->calculate([$this->item('dev', '1', '1')], stageOverrides: ['discovery' => ['fixed' => null]], rates: ['low' => 20000, 'mid' => 20000, 'high' => 20000]);

		$this->assertSame($this->totals($result, 'low'), $this->totals($result, 'high'));
	}

	public function test_min_greater_than_max_is_rejected(): void
	{
		$this->expectException(PricingException::class);

		$this->calculate([$this->item('dev', '10', '5')]);
	}

	public function test_unknown_stage_is_rejected(): void
	{
		$this->expectException(PricingException::class);

		$this->calculate([$this->item('marketing', '1', '1')]);
	}

	public function test_malformed_hours_are_rejected(): void
	{
		$this->expectException(PricingException::class);

		$this->calculate([$this->item('dev', '1.234', '2')]);
	}
}
