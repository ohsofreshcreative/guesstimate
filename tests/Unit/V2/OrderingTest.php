<?php

namespace Tests\Unit\V2;

use App\Guesstimate\Offers\BudgetSettings;
use PHPUnit\Framework\TestCase;

/**
 * MySQL sortuje klucze obiektów w kolumnach JSON (po długości, potem alfabetycznie), więc z bazy
 * wracają w kolejności: other, webapp, catalog, landing, website, woocommerce. Kolejność w kreatorze
 * musi wynikać z kodu, a nie z bazy.
 */
class OrderingTest extends TestCase
{
	private function mysqlOrderedTypes(): array
	{
		return [
			'other' => ['label' => 'Inny', 'units' => '10'],
			'webapp' => ['label' => 'Aplikacja webowa', 'units' => '0'],
			'catalog' => ['label' => 'Katalog produktów', 'units' => '5'],
			'landing' => ['label' => 'Landing page', 'units' => '0'],
			'website' => ['label' => 'Strona internetowa', 'units' => '0'],
			'woocommerce' => ['label' => 'Sklep internetowy', 'units' => '8'],
		];
	}

	public function test_project_types_have_fixed_order_without_webapp(): void
	{
		$types = BudgetSettings::types(['project_types' => $this->mysqlOrderedTypes()]);

		$this->assertSame(['website', 'catalog', 'woocommerce', 'landing', 'other'], array_keys($types));
		$this->assertSame('5', $types['catalog']['units']);
		$this->assertSame('8', $types['woocommerce']['units']);
		$this->assertSame('0', $types['other']['units'], '„Inny” nie dolicza jednostek z ustawień');
	}

	public function test_retired_type_is_kept_for_an_offer_that_already_has_it(): void
	{
		$types = BudgetSettings::types(['project_types' => $this->mysqlOrderedTypes()], 'webapp');

		$this->assertSame(['website', 'catalog', 'woocommerce', 'landing', 'other', 'webapp'], array_keys($types));
	}

	public function test_ux_scopes_optimized_first(): void
	{
		$scopes = BudgetSettings::uxScopes([
			'full' => ['label' => 'Pełny UX', 'min' => '32', 'max' => '32'],
			'optimized' => ['label' => 'Zoptymalizowany UX', 'min' => '16', 'max' => '16'],
		]);

		$this->assertSame(['optimized', 'full'], array_keys($scopes));
	}

	public function test_other_uses_custom_units(): void
	{
		$snapshot = ['project_types' => $this->mysqlOrderedTypes(), 'page_hours' => [], 'workshops_amount' => 0, 'budget_split' => [], 'rounding' => []];

		$this->assertSame('12', BudgetSettings::budget($snapshot, 'other', null, '12')['type_units']);
		$this->assertSame('0', BudgetSettings::budget($snapshot, 'other')['type_units']);
		$this->assertSame('8', BudgetSettings::budget($snapshot, 'woocommerce')['type_units']);
		// Edycja istniejącej oferty „Inny” bez nowej wartości zachowuje zapisaną liczbę.
		$this->assertSame('15', BudgetSettings::budget($snapshot, 'other', ['project_type' => 'other', 'type_units' => '15'])['type_units']);
		// Zmiana z „Inny” na sklep bierze jednostki z ustawień.
		$this->assertSame('8', BudgetSettings::budget($snapshot, 'woocommerce', ['project_type' => 'other', 'type_units' => '15'])['type_units']);
	}
}
