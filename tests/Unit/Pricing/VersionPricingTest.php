<?php

namespace Tests\Unit\Pricing;

use App\Guesstimate\Offers\VersionPricing;
use App\Guesstimate\Pricing\LineItem;
use App\Guesstimate\Pricing\PricingEngine;
use App\Guesstimate\Sitemap\SitemapStats;

class VersionPricingTest extends PricingTestCase
{
	private function snapshot(array $nodes, array $extra = []): array
	{
		return array_merge([
			'calc_model' => 'hourly',
			'rates' => self::RATES,
			'ux' => ['label' => 'Pełny UX', 'min' => '32', 'max' => '32'],
			'content_stage' => 'deploy',
			'rounding' => ['hours_step' => '0', 'price_step' => 1],
			'stages' => [
				['key' => 'discovery', 'name' => 'Discovery', 'enabled' => true, 'in_pool' => false, 'percent' => '0', 'fixed_amount' => null],
				['key' => 'ux', 'name' => 'UX Design', 'enabled' => true, 'in_pool' => true, 'percent' => '20', 'fixed_amount' => null],
				['key' => 'ui', 'name' => 'UI Design', 'enabled' => true, 'in_pool' => true, 'percent' => '30', 'fixed_amount' => null],
				['key' => 'dev', 'name' => 'Development', 'enabled' => true, 'in_pool' => true, 'percent' => '50', 'fixed_amount' => null],
				['key' => 'deploy', 'name' => 'Wdrożenie', 'enabled' => true, 'in_pool' => false, 'percent' => '0', 'fixed_amount' => null],
				['key' => 'other', 'name' => 'Pozostałe', 'enabled' => true, 'in_pool' => false, 'percent' => '0', 'fixed_amount' => null],
			],
			'templates' => [
				['id' => 1, 'name' => 'Strona główna', 'kind' => 'unique', 'ui_min' => '6', 'ui_max' => '8', 'dev_min' => '8', 'dev_max' => '12'],
				['id' => 2, 'name' => 'Karta produktu', 'kind' => 'template', 'ui_min' => '4', 'ui_max' => '6', 'dev_min' => '8', 'dev_max' => '12'],
				['id' => 3, 'name' => 'Nieużywany', 'kind' => 'template', 'ui_min' => '50', 'ui_max' => '50', 'dev_min' => '50', 'dev_max' => '50'],
			],
			'nodes' => $nodes,
		], $extra);
	}

	private function node(int $id, string $name, int $template, int $instances = 1, bool $content = false, string $cMin = '0', string $cMax = '0'): array
	{
		return ['id' => $id, 'parent_id' => null, 'name' => $name, 'template_id' => $template, 'instances' => $instances, 'content_entry' => $content, 'content_min' => $cMin, 'content_max' => $cMax, 'extra_min' => null, 'extra_max' => null];
	}

	public function test_template_cost_does_not_depend_on_number_of_products(): void
	{
		$one = (new PricingEngine)->calculate(VersionPricing::toInput($this->snapshot([$this->node(1, 'Produkt', 2, 1)])));
		$many = (new PricingEngine)->calculate(VersionPricing::toInput($this->snapshot([$this->node(1, 'Produkt', 2, 150)])));

		$this->assertSame($this->stage($one, 'ui')['hours'], $this->stage($many, 'ui')['hours']);
		$this->assertSame($this->stage($one, 'dev')['hours'], $this->stage($many, 'dev')['hours']);
	}

	public function test_content_entry_scales_with_instances_in_its_own_stage(): void
	{
		$result = (new PricingEngine)->calculate(VersionPricing::toInput($this->snapshot([$this->node(1, 'Produkt', 2, 150, true, '0.1', '0.2')])));

		$this->assertSame(['min' => '15.00', 'max' => '30.00'], $this->stage($result, 'deploy')['hours']);
		$this->assertSame(['min' => '8.00', 'max' => '12.00'], $this->stage($result, 'dev')['hours']);
	}

	public function test_two_nodes_on_one_template_are_charged_once(): void
	{
		$result = (new PricingEngine)->calculate(VersionPricing::toInput($this->snapshot([
			$this->node(1, 'Produkt A', 2),
			$this->node(2, 'Produkt B', 2),
		])));

		$this->assertSame(['min' => '4.00', 'max' => '6.00'], $this->stage($result, 'ui')['hours']);
	}

	public function test_unused_templates_are_not_charged(): void
	{
		$items = VersionPricing::items($this->snapshot([$this->node(1, 'Start', 1)]));
		$labels = array_map(fn (LineItem $item) => $item->label, $items);

		$this->assertNotContains('Szablon: Nieużywany', $labels);
	}

	public function test_ux_hours_come_only_from_the_ux_scope(): void
	{
		$result = (new PricingEngine)->calculate(VersionPricing::toInput($this->snapshot([
			$this->node(1, 'Start', 1),
			$this->node(2, 'Produkt', 2, 150),
		])));

		$this->assertSame(['min' => '32.00', 'max' => '32.00'], $this->stage($result, 'ux')['hours']);
	}

	public function test_page_extra_hours_go_to_development(): void
	{
		$node = $this->node(1, 'Start', 1);
		$node['extra_min'] = '2';
		$node['extra_max'] = '3';
		$result = (new PricingEngine)->calculate(VersionPricing::toInput($this->snapshot([$node])));

		$this->assertSame(['min' => '10.00', 'max' => '15.00'], $this->stage($result, 'dev')['hours']);
	}

	public function test_features_work_items_stage_hours_and_costs_are_included(): void
	{
		$snapshot = $this->snapshot([$this->node(1, 'Start', 1)], [
			'features' => [['name' => 'Polylang', 'stage_key' => 'dev', 'hours_min' => '6', 'hours_max' => '10']],
			'work_items' => [['name' => 'Szkolenie', 'stage_key' => 'other', 'hours_min' => '2', 'hours_max' => '3']],
			'costs' => [['name' => 'Hosting', 'amount' => 5000, 'quantity' => '1', 'billing' => 'monthly']],
		]);
		$snapshot['stages'][4]['manual_min'] = '4';
		$snapshot['stages'][4]['manual_max'] = '6';

		$result = (new PricingEngine)->calculate(VersionPricing::toInput($snapshot));

		$this->assertSame(['min' => '14.00', 'max' => '22.00'], $this->stage($result, 'dev')['hours']);
		$this->assertSame(['min' => '2.00', 'max' => '3.00'], $this->stage($result, 'other')['hours']);
		$this->assertSame(['min' => '4.00', 'max' => '6.00'], $this->stage($result, 'deploy')['hours']);
		$this->assertSame(5000, $result['costs']['monthly']);
	}

	public function test_sitemap_stats_distinguish_pages_templates_unique_pages_and_content(): void
	{
		$snapshot = $this->snapshot([
			$this->node(1, 'Strona główna', 1),
			$this->node(2, 'Produkt', 2, 150, true),
			$this->node(3, 'Produkt promocyjny', 2, 1, true),
		]);

		$this->assertSame(
			['pages' => 152, 'templates' => 2, 'unique_pages' => 1, 'content_items' => 151],
			SitemapStats::compute($snapshot['templates'], $snapshot['nodes']),
		);
	}
}
