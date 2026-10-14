<?php

namespace Tests\Unit\Sitemap;

use App\Guesstimate\Sitemap\SitemapPayload;
use PHPUnit\Framework\TestCase;

class SitemapPayloadTest extends TestCase
{
	private function template(string $key, string $kind = 'template', array $hours = []): array
	{
		return array_merge(['key' => $key, 'name' => 'Szablon ' . $key, 'kind' => $kind, 'page_type_id' => 1, 'page_type_name' => 'Typ', 'ui_min' => '2', 'ui_max' => '3', 'dev_min' => '4', 'dev_max' => '6', 'content_min' => '0', 'content_max' => '0'], $hours);
	}

	private function node(string $name, int $depth, ?string $template, array $extra = []): array
	{
		return array_merge(['name' => $name, 'depth' => $depth, 'template_key' => $template, 'instances' => 1], $extra);
	}

	public function test_builds_hierarchy_from_depth(): void
	{
		$result = SitemapPayload::parse([
			'templates' => [$this->template('home', 'unique'), $this->template('std')],
			'nodes' => [
				$this->node('Strona główna', 0, 'home'),
				$this->node('Oferta', 1, 'std'),
				$this->node('Kategoria', 2, 'std'),
				$this->node('Kontakt', 1, 'std'),
			],
		]);

		$this->assertSame([], $result['errors']);
		$this->assertSame([null, 0, 1, 0], array_column($result['nodes'], 'parent_index'));
	}

	public function test_rejects_jump_in_hierarchy_and_missing_type_and_name(): void
	{
		$result = SitemapPayload::parse([
			'templates' => [$this->template('std')],
			'nodes' => [$this->node('A', 0, 'std'), $this->node('B', 2, 'std'), $this->node('', 0, null)],
		]);

		$this->assertCount(3, $result['errors']);
	}

	public function test_unique_template_used_twice_is_split_into_own_copies(): void
	{
		$result = SitemapPayload::parse([
			'templates' => [$this->template('ind', 'unique')],
			'nodes' => [$this->node('O firmie', 0, 'ind'), $this->node('Kariera', 0, 'ind')],
		]);

		$this->assertCount(2, $result['templates']);
		$this->assertNotSame($result['nodes'][0]['template_key'], $result['nodes'][1]['template_key']);
	}

	public function test_unique_pages_always_have_one_instance_and_templates_keep_many(): void
	{
		$result = SitemapPayload::parse([
			'templates' => [$this->template('ind', 'unique'), $this->template('prod')],
			'nodes' => [$this->node('Start', 0, 'ind', ['instances' => 5]), $this->node('Produkt', 0, 'prod', ['instances' => 150])],
		]);

		$this->assertSame([1, 150], array_column($result['nodes'], 'instances'));
	}

	public function test_unused_templates_are_dropped(): void
	{
		$result = SitemapPayload::parse([
			'templates' => [$this->template('a'), $this->template('b')],
			'nodes' => [$this->node('Strona', 0, 'a')],
		]);

		$this->assertSame(['a'], array_column($result['templates'], 'key'));
	}

	public function test_hours_accept_polish_comma_and_reject_min_above_max(): void
	{
		$ok = SitemapPayload::parse([
			'templates' => [$this->template('a', 'template', ['ui_min' => '2,5', 'ui_max' => '3,25'])],
			'nodes' => [$this->node('Strona', 0, 'a', ['content_entry' => true, 'content_min' => '0,1', 'content_max' => '0,2'])],
		]);
		$this->assertSame('2.50', $ok['templates'][0]['ui_min']);
		$this->assertSame('0.10', $ok['nodes'][0]['content_min']);

		$bad = SitemapPayload::parse([
			'templates' => [$this->template('a', 'template', ['dev_min' => '8', 'dev_max' => '4'])],
			'nodes' => [$this->node('Strona', 0, 'a')],
		]);
		$this->assertNotEmpty($bad['errors']);
	}

	public function test_strips_markup_from_names(): void
	{
		$result = SitemapPayload::parse([
			'templates' => [$this->template('a')],
			'nodes' => [$this->node('<b>Oferta</b><script>x</script>', 0, 'a')],
		]);

		$this->assertSame('Ofertax', $result['nodes'][0]['name']);
	}
}
