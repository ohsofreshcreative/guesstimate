<?php

namespace Tests\Unit\Pricing;

use App\Guesstimate\Offers\VersionPricing;
use App\Guesstimate\Pricing\PricingEngine;

/**
 * Odtworzenie przykładu z calc.xlsx: 8 podstron × 4–6 h + zoptymalizowany UX 16 h,
 * stawki 150 / 180 / 200 zł/h, Discovery 2 000 zł, podział reszty 20 / 30 / 50%.
 */
class ExcelCompatibilityTest extends PricingTestCase
{
	private function excelSnapshot(): array
	{
		$templates = [];
		$nodes = [];

		for ($i = 1; $i <= 8; $i++) {
			// W Excelu każda podstrona kosztuje 4–6 h, więc w aplikacji każda jest podstroną indywidualną.
			$templates[] = ['id' => $i, 'name' => "Podstrona {$i}", 'kind' => 'unique', 'ui_min' => '2', 'ui_max' => '3', 'dev_min' => '2', 'dev_max' => '3'];
			$nodes[] = ['id' => $i, 'name' => "Podstrona {$i}", 'template_id' => $i, 'instances' => 1, 'content_entry' => false, 'content_min' => '0', 'content_max' => '0'];
		}

		return [
			'calc_model' => 'percent',
			'rates' => self::RATES,
			'ux' => ['label' => 'Zoptymalizowany UX', 'min' => '16', 'max' => '16'],
			'content_stage' => 'deploy',
			'rounding' => ['hours_step' => '0', 'price_step' => 1],
			'stages' => [
				['key' => 'discovery', 'name' => 'Warsztaty Discovery', 'enabled' => true, 'in_pool' => false, 'percent' => '0', 'fixed_amount' => 200000],
				['key' => 'ux', 'name' => 'UX Design', 'enabled' => true, 'in_pool' => true, 'percent' => '20', 'fixed_amount' => null],
				['key' => 'ui', 'name' => 'UI Design', 'enabled' => true, 'in_pool' => true, 'percent' => '30', 'fixed_amount' => null],
				['key' => 'dev', 'name' => 'Development', 'enabled' => true, 'in_pool' => true, 'percent' => '50', 'fixed_amount' => null],
				['key' => 'deploy', 'name' => 'Wdrożenie', 'enabled' => true, 'in_pool' => false, 'percent' => '0', 'fixed_amount' => null],
				['key' => 'other', 'name' => 'Pozostałe', 'enabled' => true, 'in_pool' => false, 'percent' => '0', 'fixed_amount' => null],
			],
			'templates' => $templates,
			'nodes' => $nodes,
		];
	}

	public function test_hours_match_the_spreadsheet(): void
	{
		$result = (new PricingEngine)->calculate(VersionPricing::toInput($this->excelSnapshot()));

		$this->assertSame(['min' => '48.00', 'max' => '64.00'], $result['hours']);
	}

	public function test_totals_for_every_rate_match_the_spreadsheet_matrix(): void
	{
		$result = (new PricingEngine)->calculate(VersionPricing::toInput($this->excelSnapshot()));

		$this->assertSame(['min' => 720000, 'max' => 960000], $this->totals($result, 'low'));
		$this->assertSame(['min' => 864000, 'max' => 1152000], $this->totals($result, 'mid'));
		$this->assertSame(['min' => 960000, 'max' => 1280000], $this->totals($result, 'high'));
		$this->assertSame(['min' => 720000, 'max' => 1280000], $result['range']);
	}

	public function test_stage_split_for_high_risk_matches_the_spreadsheet(): void
	{
		$result = (new PricingEngine)->calculate(VersionPricing::toInput($this->excelSnapshot()));

		$this->assertSame(['min' => 200000, 'max' => 200000], $this->stage($result, 'discovery')['amounts']['high']);
		$this->assertSame(['min' => 152000, 'max' => 216000], $this->stage($result, 'ux')['amounts']['high']);
		$this->assertSame(['min' => 228000, 'max' => 324000], $this->stage($result, 'ui')['amounts']['high']);
		$this->assertSame(['min' => 380000, 'max' => 540000], $this->stage($result, 'dev')['amounts']['high']);
	}

	public function test_stage_split_is_calculated_for_every_risk_level(): void
	{
		$result = (new PricingEngine)->calculate(VersionPricing::toInput($this->excelSnapshot()));

		$this->assertSame(['min' => 104000, 'max' => 152000], $this->stage($result, 'ux')['amounts']['low']);
		$this->assertSame(['min' => 156000, 'max' => 228000], $this->stage($result, 'ui')['amounts']['low']);
		$this->assertSame(['min' => 260000, 'max' => 380000], $this->stage($result, 'dev')['amounts']['low']);
		$this->assertSame(['min' => 132800, 'max' => 190400], $this->stage($result, 'ux')['amounts']['mid']);
		$this->assertSame(['min' => 199200, 'max' => 285600], $this->stage($result, 'ui')['amounts']['mid']);
		$this->assertSame(['min' => 332000, 'max' => 476000], $this->stage($result, 'dev')['amounts']['mid']);
	}
}
