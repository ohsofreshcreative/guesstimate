<?php

namespace Tests\Unit\Pricing;

use App\Guesstimate\Pricing\CalcModel;
use App\Guesstimate\Pricing\Cost;
use App\Guesstimate\Pricing\LineItem;
use App\Guesstimate\Pricing\PricingEngine;
use App\Guesstimate\Pricing\PricingInput;
use App\Guesstimate\Pricing\Rounding;
use App\Guesstimate\Pricing\Stage;
use PHPUnit\Framework\TestCase;

abstract class PricingTestCase extends TestCase
{
	protected const RATES = ['low' => 15000, 'mid' => 18000, 'high' => 20000];

	/**
	 * Etapy jak w domyślnych ustawieniach: Discovery 2 000 zł, pula UX/UI/Dev 20/30/50%.
	 *
	 * @return list<Stage>
	 */
	protected function stages(array $overrides = []): array
	{
		$defaults = [
			'discovery' => ['Discovery', true, false, '0', 200000],
			'ux' => ['UX Design', true, true, '20', null],
			'ui' => ['UI Design', true, true, '30', null],
			'dev' => ['Development', true, true, '50', null],
			'deploy' => ['Wdrożenie', true, false, '0', null],
			'other' => ['Pozostałe', true, false, '0', null],
		];

		$stages = [];

		foreach ($defaults as $key => [$name, $enabled, $inPool, $percent, $fixed]) {
			$o = $overrides[$key] ?? [];
			$stages[] = new Stage(
				key: $key,
				name: $name,
				enabled: $o['enabled'] ?? $enabled,
				inPool: $o['in_pool'] ?? $inPool,
				percent: $o['percent'] ?? $percent,
				fixedAmount: array_key_exists('fixed', $o) ? $o['fixed'] : $fixed,
			);
		}

		return $stages;
	}

	protected function item(string $stage, string $min, string $max, string $label = 'Pozycja'): LineItem
	{
		return new LineItem(LineItem::SOURCE_WORK, $label, $stage, $min, $max);
	}

	/**
	 * @param  list<LineItem>  $items
	 * @param  list<Cost>  $costs
	 */
	protected function calculate(
		array $items,
		CalcModel $model = CalcModel::Hourly,
		array $stageOverrides = [],
		array $rates = self::RATES,
		array $costs = [],
		Rounding $rounding = new Rounding,
	): array {
		return (new PricingEngine)->calculate(new PricingInput($model, $rates, $this->stages($stageOverrides), $items, $costs, $rounding));
	}

	protected function stage(array $result, string $key): array
	{
		foreach ($result['stages'] as $stage) {
			if ($stage['key'] === $key) {
				return $stage;
			}
		}

		$this->fail("Brak etapu {$key} w wyniku.");
	}

	protected function totals(array $result, string $level): array
	{
		return $result['levels'][$level]['total'];
	}
}
