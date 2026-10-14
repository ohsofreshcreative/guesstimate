<?php

namespace App\Guesstimate\Pricing;

/**
 * Kompletny, niezmienny opis wyceny: wszystko, czego silnik potrzebuje, bez odwołań do bazy i WP.
 */
final class PricingInput
{
	/**
	 * @param  array<string, int>  $rates  stawki w groszach: ['low' => 15000, 'mid' => 18000, 'high' => 20000]
	 * @param  list<Stage>  $stages
	 * @param  list<LineItem>  $items
	 * @param  list<Cost>  $costs
	 */
	public function __construct(
		public readonly CalcModel $model,
		public readonly array $rates,
		public readonly array $stages,
		public readonly array $items,
		public readonly array $costs = [],
		public readonly Rounding $rounding = new Rounding,
	) {
	}

	public function rate(RiskLevel $level): int
	{
		return $this->rates[$level->value];
	}
}
