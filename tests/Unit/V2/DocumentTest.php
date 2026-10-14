<?php

namespace {
	if (! function_exists('esc_html')) {
		function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
	}
	if (! function_exists('esc_attr')) {
		function esc_attr($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
	}
}

namespace Tests\Unit\V2 {

	use App\Guesstimate\Document\DocumentView;
	use App\Guesstimate\Document\Markup;
	use App\Guesstimate\Document\Media;
	use PHPUnit\Framework\TestCase;

	class DocumentTest extends TestCase
	{
		public function test_markup_highlights_and_escapes(): void
		{
			$parts = Markup::parts('Strona, która **przyspieszy** <b>rozwój</b>');

			$this->assertSame([['Strona, która ', false], ['przyspieszy', true], [' <b>rozwój</b>', false]], $parts);
			$this->assertSame('Strona, która <span class="text-orange">przyspieszy</span> &lt;b&gt;rozwój&lt;/b&gt;', Markup::html($parts));
		}

		public function test_markup_without_highlight_and_empty(): void
		{
			$this->assertSame([['Zwykły tekst', false]], Markup::parts('Zwykły tekst'));
			$this->assertSame([], Markup::parts(''));
			$this->assertSame([['****', false]], Markup::parts('****'));
		}

		public function test_ratio_warning(): void
		{
			// Strony dokumentu: A4 poziomo (297 × 210).
			$this->assertFalse(Media::ratioWarning(3508, 2480));
			$this->assertFalse(Media::ratioWarning(1800, 1273));
			$this->assertTrue(Media::ratioWarning(1920, 1080));
			$this->assertFalse(Media::ratioWarning(null, null));
		}

		public function test_legacy_snapshot_becomes_document(): void
		{
			$view = DocumentView::fromSnapshot([
				'schema' => 1, 'title' => 'Stara', 'content' => ['situation' => '<p>x</p>', 'blocks' => [['title' => '', 'html' => '']]],
				'summary' => ['features' => [['name' => 'Polylang']]],
			]);

			$this->assertSame(['cover', 'situation', 'pricing'], array_column($view['document']['pages'], 'key'));
			$this->assertSame(['Polylang'], $view['document']['includes']);
		}

		public function test_new_snapshot_keeps_data_and_gets_editor_defaults(): void
		{
			$doc = ['pages' => [['key' => 'cover'], ['key' => 'pricing']], 'x' => 1];
			$view = DocumentView::fromSnapshot(['schema' => 2, 'project_type' => 'Sklep internetowy', 'document' => $doc])['document'];

			$this->assertSame(1, $view['x']);
			$this->assertSame(['key' => 'cover', 'layout' => 'full', 'image' => null, 'hidden' => null, 'heading' => []], $view['pages'][0]);
			$this->assertSame([['Wariant cenowy –', true], [' sklep internetowy', false]], $view['pages'][1]['heading']);
		}

		public function test_editor_snapshot_is_not_changed(): void
		{
			$page = ['key' => 'situation', 'layout' => 'image_text', 'image' => ['id' => 5, 'url' => 'https://x/a.jpg'], 'hidden' => null, 'heading' => [['Własny', false]]];
			$view = DocumentView::fromSnapshot(['schema' => 2, 'document' => ['pages' => [$page]]])['document'];

			$this->assertSame($page, $view['pages'][0]);
		}

		public function test_image_is_ignored_in_layout_without_media(): void
		{
			$page = ['key' => 'pricing', 'layout' => 'text_image', 'image' => ['id' => 5]];
			$view = DocumentView::fromSnapshot(['schema' => 2, 'document' => ['pages' => [$page]]])['document'];

			$this->assertSame('full', $view['pages'][0]['layout']);
			$this->assertNull($view['pages'][0]['image']);
		}
	}
}
