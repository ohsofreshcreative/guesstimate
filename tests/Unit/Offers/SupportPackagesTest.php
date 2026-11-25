<?php

namespace {
	if (! function_exists('sanitize_text_field')) {
		function sanitize_text_field($text) { return trim(strip_tags((string) $text)); }
	}

	if (! function_exists('sanitize_textarea_field')) {
		function sanitize_textarea_field($text) { return trim(strip_tags((string) $text)); }
	}
}

namespace Tests\Unit\Offers {

	use App\Guesstimate\Document\Pages;
	use App\Guesstimate\Offers\SupportPackages;
	use App\Guesstimate\Support\Format;
	use PHPUnit\Framework\TestCase;

	/**
	 * Oferta pakietów support: kształt danych, walidacja z formularza, strony dokumentu i braki przed publikacją.
	 */
	class SupportPackagesTest extends TestCase
	{
		public function test_defaults_match_the_attachment(): void
		{
			$support = SupportPackages::normalize(SupportPackages::DEFAULTS);

			$this->assertSame(['Bezpieczeństwo', 'Niezawodność', 'Rozwój'], array_column($support['packages'], 'name'));
			$this->assertSame([45000, 75000, 110000], array_column($support['packages'], 'price'));
			$this->assertCount(12, $support['points']);

			foreach ($support['points'] as $point) {
				$this->assertCount(3, $point['cells']);
			}

			$this->assertSame([], SupportPackages::problems($support));
			$this->assertSame([45000, 110000], SupportPackages::range($support));
		}

		public function test_packages_page_strikes_points_outside_package_and_uses_own_labels(): void
		{
			$present = SupportPackages::present(SupportPackages::DEFAULTS, 'PLN');
			$first = $present['packages'][0];
			$points = array_column($first['points'], 'on', 'text');

			$this->assertSame(Format::money(45000), $first['price']);
			$this->assertSame('netto/co 3 mies.', $first['price_note']);
			$this->assertSame(Format::money(15000), $first['monthly']);
			$this->assertSame(Format::money(36667), $present['packages'][2]['monthly']); // 1100 / 3, HALF_UP
			$this->assertTrue($points['Gwarantowany czas reakcji (max. 4h)']);
			$this->assertFalse($points['Nowoczesne środowisko VM']);
			$this->assertSame('Gwarantowany czas reakcji (max. 2h)', $present['packages'][2]['points'][5]['text']);
		}

		public function test_legend_columns_follow_chosen_group_or_first_package(): void
		{
			$present = SupportPackages::present(SupportPackages::DEFAULTS, 'PLN');

			$this->assertSame(['orange', 'flame', 'indigo'], array_column($present['legend'], 'color'));
			$this->assertContains('Automatyzacje strony', array_column($present['legend'][1]['items'], 'name'));
			$this->assertContains('Kompresja grafik', array_column($present['legend'][2]['items'], 'name'));

			$cells = [['on' => false, 'label' => ''], ['on' => true, 'label' => ''], ['on' => true, 'label' => '']];
			$this->assertSame(1, SupportPackages::legendColumn(['group' => null, 'cells' => $cells]));
			$this->assertSame(0, SupportPackages::legendColumn(['group' => 0, 'cells' => $cells]));
			$this->assertSame(2, SupportPackages::legendColumn(['group' => null, 'cells' => array_fill(0, 3, ['on' => false, 'label' => ''])]));
		}

		public function test_validation_parses_prices_and_aligns_cells_with_packages(): void
		{
			$result = SupportPackages::validate([
				'packages' => [
					['name' => 'Start', 'color' => 'orange', 'price' => '1 100,50', 'price_note' => 'netto/mies.'],
					['name' => 'Pro', 'color' => 'unknown', 'price' => 'dużo'],
				],
				'points' => [
					['name' => 'Kopia danych', 'description' => 'Codziennie', 'group' => '1', 'cells' => [['on' => true, 'label' => ''], ['on' => '1', 'label' => 'Kopia co godzinę'], ['on' => true]]],
					['name' => '', 'description' => '', 'cells' => []],
					['name' => '', 'description' => 'Bez nazwy', 'group' => '7'],
				],
			]);

			$this->assertSame(110050, $result['data']['packages'][0]['price']);
			$this->assertNull($result['data']['packages'][1]['price']);
			$this->assertArrayHasKey('packages.1.price', $result['errors']);
			$this->assertArrayHasKey('packages.1.color', $result['errors']);
			$this->assertSame('flame', $result['data']['packages'][1]['color']);

			$this->assertCount(2, $result['data']['points']); // pusty punkt pominięty
			$this->assertSame([['on' => true, 'label' => ''], ['on' => true, 'label' => 'Kopia co godzinę']], $result['data']['points'][0]['cells']);
			$this->assertSame(1, $result['data']['points'][0]['group']);
			$this->assertNull($result['data']['points'][1]['group']);
			$this->assertArrayHasKey('points.1.name', $result['errors']);
		}

		public function test_problems_block_publication_without_packages_or_prices(): void
		{
			$this->assertSame(['Dodaj co najmniej jeden pakiet support.'], SupportPackages::problems(['packages' => [], 'points' => []]));
			$this->assertSame(['Pakiet „Start”: podaj cenę.'], SupportPackages::problems(['packages' => [['name' => 'Start', 'price' => null]]]));
			$this->assertSame([null, null], SupportPackages::range(['packages' => [['name' => 'Start', 'price' => null]]]));
		}

		public function test_billing_period_falls_back_to_price_note_and_is_validated(): void
		{
			$old = SupportPackages::normalize(['packages' => [['name' => 'A', 'price' => 45000, 'price_note' => 'netto/co 6 mies.'], ['name' => 'B', 'price' => 45000]]]);
			$this->assertSame([6, 1], array_column($old['packages'], 'months'));

			$present = SupportPackages::present($old, 'PLN');
			$this->assertSame(Format::money(7500), $present['packages'][0]['monthly']);
			$this->assertNull($present['packages'][1]['monthly']);

			$result = SupportPackages::validate(['packages' => [['name' => 'A', 'price' => '450', 'months' => '13']], 'points' => []]);
			$this->assertArrayHasKey('packages.0.months', $result['errors']);
			$this->assertSame(3, SupportPackages::validate(['packages' => [['name' => 'A', 'price' => '450', 'months' => 3]], 'points' => []])['data']['packages'][0]['months']);
		}

				public function test_editor_state_shows_prices_as_form_text(): void
		{
			$state = SupportPackages::editorState(['packages' => [['name' => 'A', 'price' => 110050], ['name' => 'B', 'price' => 45000]], 'points' => []]);

			$this->assertSame(['1100,50', '450'], array_column($state['packages'], 'price'));
		}

		public function test_support_document_has_its_own_pages(): void
		{
			$this->assertSame(['cover', 'support_packages', 'support_legend', 'contact'], array_keys(Pages::base(true)));
			$this->assertSame(array_keys(Pages::TYPES), array_keys(Pages::base(false)));
			$this->assertTrue(Pages::hasHtml('support_packages'));
			$this->assertTrue(Pages::hasHtml('custom_table'));
			$this->assertFalse(Pages::hasHtml('pricing'));
			$this->assertFalse(Pages::extraType('support_packages'));
			$this->assertSame('support_legend', Pages::type('support_legend'));
			$this->assertSame(['cover', 'support_packages', 'support_legend', 'contact'], Pages::order(array_fill_keys(['contact', 'support_legend', 'cover', 'support_packages'], []), ['cover', 'support_packages']));
		}
	}
}
