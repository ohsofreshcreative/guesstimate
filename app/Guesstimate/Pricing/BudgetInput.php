<?php

namespace App\Guesstimate\Pricing;

/**
 * Wejście modelu budżetowego (silnik 2.x): cena = godziny × stawka, warsztaty Discovery jako kwota stała
 * wewnątrz ceny, reszta prezentowana jako podział UX / UI / Development.
 */
final class BudgetInput
{
	/**
	 * @param  array<string, int>  $rates  stawki w groszach (low/mid/high)
	 * @param  list<LineItem>  $items  pozycje godzinowe; stageKey = grupa (BudgetEngine::GROUPS)
	 * @param  list<Cost>  $costs  koszty zewnętrzne — poza ceną realizacji
	 * @param  int  $workshops  kwota warsztatów Discovery w groszach (część ceny)
	 * @param  array{ux: string, ui: string, dev: string}  $split  podział reszty w procentach
	 */
	public function __construct(
		public readonly array $rates,
		public readonly array $items,
		public readonly array $costs = [],
		public readonly int $workshops = 0,
		public readonly array $split = ['ux' => '20', 'ui' => '30', 'dev' => '50'],
		public readonly Rounding $rounding = new Rounding,
	) {
	}
}
