<?php

namespace {
	if (! function_exists('wp_json_encode')) {
		function wp_json_encode($data, $options = 0) { return json_encode($data, $options); }
	}

	if (! function_exists('wp_kses')) {
		function wp_kses($html, $allowed = [], $protocols = []) { return strip_tags((string) $html, '<p><strong><em><ul><ol><li><a><br>'); }
	}
}

namespace Tests\Unit\V2 {

	use App\Guesstimate\Document\DocumentForm;
	use App\Guesstimate\Document\DocumentSettings;
	use App\Guesstimate\Document\MediaRefs;
	use App\Guesstimate\Document\Pages;
	use App\Guesstimate\Offers\PublicationService;
	use PHPUnit\Framework\TestCase;

	/**
	 * Edytor wizualny dokumentu: układy stron, walidacja wyglądu, pomiar dopasowania, referencje mediów.
	 */
	class EditorTest extends TestCase
	{
		private function current(): array
		{
			$pages = [];

			foreach (array_keys(Pages::TYPES) as $key) {
				$pages[$key] = Pages::blank($key);
			}

			return ['pages' => $pages, 'cover_heading' => 'X'];
		}

		public function test_layouts_per_page_type(): void
		{
			$this->assertSame('text_image', Pages::defaultLayout('situation'));
			$this->assertSame('full', Pages::layout('pricing', 'tiles'));
			$this->assertSame('tiles', Pages::layout('references', null));
			$this->assertSame('image_text', Pages::layout('contact', 'image_text'));
			$this->assertTrue(Pages::hasMedia('image_text'));
			$this->assertFalse(Pages::hasMedia('tiles'));

			foreach (Pages::PAGE_LAYOUTS as $key => $layouts) {
				$this->assertTrue(isset(Pages::TYPES[$key]) || $key === Pages::CUSTOM, $key);
				$this->assertSame([], array_diff($layouts, array_keys(Pages::LAYOUTS)));
			}
		}

		public function test_design_validation(): void
		{
			$result = DocumentForm::design([
				'situation' => ['layout' => 'full', 'heading' => 'Jak **my** to widzimy?'],
				'pricing' => ['layout' => 'tiles', 'enabled' => false],
				'extras' => ['enabled' => false],
				'nieznana' => ['layout' => 'full'],
			], $this->current());

			$this->assertNull($result['data']);
			$this->assertArrayHasKey('pages.pricing.layout', $result['errors']);
			$this->assertArrayHasKey('pages.pricing.enabled', $result['errors']);
			$this->assertSame('full', $result['draft']['pages']['situation']['layout']);
			$this->assertSame('Jak **my** to widzimy?', $result['draft']['pages']['situation']['heading']);
			$this->assertFalse($result['draft']['pages']['extras']['enabled']);
			$this->assertArrayNotHasKey('nieznana', $result['draft']['pages']);
		}

		public function test_page_order_drops_unknown_and_appends_missing_pages(): void
		{
			$pages = $this->current()['pages'];
			$pages['extra_10'] = ['type' => 'custom'];
			$pages['extra_2'] = ['type' => 'pricing'];

			$order = Pages::order($pages, ['contact', 'nieznana', 'extra_10', 'contact', 'cover']);

			$this->assertSame(['contact', 'extra_10', 'cover', 'situation', 'solution', 'pricing', 'extras', 'references', 'next_steps', 'extra_2'], $order);
			$this->assertSame([...array_keys(Pages::TYPES), 'extra_2', 'extra_10'], Pages::order($pages, null));
		}

		public function test_extra_page_types_and_labels(): void
		{
			$this->assertTrue(Pages::isExtra('extra_1'));
			$this->assertFalse(Pages::isExtra('extra_0'));
			$this->assertFalse(Pages::isExtra('pricing'));
			$this->assertTrue(Pages::extraType('custom'));
			$this->assertTrue(Pages::extraType('pricing'));
			$this->assertFalse(Pages::extraType('situation'));
			$this->assertFalse(Pages::extraType('nieznana'));
			$this->assertSame('Wycena (kopia)', Pages::label('extra_1', ['type' => 'pricing']));
			$this->assertSame('Nasz zespół', Pages::label('extra_2', ['type' => 'custom', 'heading' => 'Nasz **zespół**']));
			$this->assertSame(Pages::CUSTOM_LABEL, Pages::label('extra_3', ['type' => 'custom']));
		}

		public function test_design_adds_reorders_and_removes_extra_pages(): void
		{
			$current = $this->current() + ['page_order' => array_keys(Pages::TYPES)];
			$current['pages']['extra_5'] = ['type' => 'custom', 'html' => '<p>Stara</p>'] + Pages::blank('custom');
			$order = ['cover', 'extra_1', 'situation', 'solution', 'pricing', 'extra_2', 'extras', 'references', 'next_steps', 'contact'];

			$result = DocumentForm::design([
				'extra_1' => ['type' => 'custom', 'layout' => 'text_image', 'heading' => 'O nas', 'html' => '<p>Treść <script>x</script><strong>ważna</strong></p>', 'enabled' => false],
				'extra_2' => ['type' => 'pricing', 'layout' => 'full'],
			], $current, $order);

			$this->assertSame([], $result['errors']);
			$data = $result['data'];
			$this->assertSame($order, $data['page_order']);
			$this->assertArrayNotHasKey('extra_5', $data['pages']);
			$this->assertSame('custom', $data['pages']['extra_1']['type']);
			$this->assertSame('text_image', $data['pages']['extra_1']['layout']);
			$this->assertSame('O nas', $data['pages']['extra_1']['heading']);
			$this->assertSame('<p>Treść x<strong>ważna</strong></p>', $data['pages']['extra_1']['html']);
			$this->assertFalse($data['pages']['extra_1']['enabled']);
			$this->assertSame('pricing', $data['pages']['extra_2']['type']);
			$this->assertArrayNotHasKey('html', $data['pages']['extra_2']);
		}

		public function test_design_rejects_invalid_extra_pages(): void
		{
			$current = $this->current();
			$result = DocumentForm::design([
				'extra_1' => ['type' => 'situation'],
				'extra_2' => ['type' => 'custom', 'layout' => 'tiles'],
			], $current, [...array_keys(Pages::TYPES), 'extra_1', 'extra_2']);

			$this->assertNull($result['data']);
			$this->assertArrayHasKey('pages.extra_1.type', $result['errors']);
			$this->assertArrayHasKey('pages.extra_2.layout', $result['errors']);
			$this->assertArrayNotHasKey('extra_1', $result['draft']['pages']);

			$this->assertArrayHasKey('page_order', DocumentForm::design([], $current, 'zła')['errors']);
		}

		public function test_design_without_order_keeps_extra_pages(): void
		{
			$current = $this->current();
			$current['pages']['extra_1'] = ['type' => 'contact'] + Pages::blank('contact');

			$result = DocumentForm::design(['contact' => ['layout' => 'image_text']], $current);

			$this->assertSame([], $result['errors']);
			$this->assertArrayHasKey('extra_1', $result['data']['pages']);
			$this->assertSame('extra_1', end($result['data']['page_order']));
		}

		public function test_default_heading_is_stored_as_null(): void
		{
			$result = DocumentForm::design(['solution' => ['heading' => Pages::HEADINGS['solution']], 'contact' => ['heading' => '  ']], $this->current());

			$this->assertSame([], $result['errors']);
			$this->assertNull($result['data']['pages']['solution']['heading']);
			$this->assertNull($result['data']['pages']['contact']['heading']);
		}

		public function test_fit_hash_depends_on_measured_state_only(): void
		{
			$doc = $this->current();
			$content = ['situation' => '<p>Tekst</p>', 'blocks' => []];
			$hash = DocumentSettings::fitHash($doc, $content);

			$other = $doc;
			$other['pages']['pricing']['layout'] = 'full';
			$other['cover_heading'] = 'Inny';
			$this->assertSame($hash, DocumentSettings::fitHash($other, $content));

			$other['pages']['situation']['layout'] = 'full';
			$this->assertNotSame($hash, DocumentSettings::fitHash($other, $content));
			$this->assertNotSame($hash, DocumentSettings::fitHash($doc, ['situation' => '<p>Tekst 2</p>']));
		}

		public function test_fingerprint_ignores_key_order_and_cover_date(): void
		{
			$a = ['number' => 'OSF/1', 'document' => ['cover' => ['date' => '2026-10-13', 'lead' => 'x'], 'pages' => [['key' => 'cover', 'layout' => 'full']]]];
			$b = ['document' => ['pages' => [['layout' => 'full', 'key' => 'cover']], 'cover' => ['lead' => 'x', 'date' => '2026-10-14']], 'number' => 'OSF/1'];

			$this->assertSame(PublicationService::fingerprint($a), PublicationService::fingerprint($b));

			$b['document']['pages'][0]['layout'] = 'text_image';
			$this->assertNotSame(PublicationService::fingerprint($a), PublicationService::fingerprint($b));
		}

		public function test_media_refs_from_snapshot(): void
		{
			$snapshot = ['document' => [
				'logo_id' => 3,
				'pages' => [
					['key' => 'cover', 'image' => null, 'background' => ['id' => 7]],
					['key' => 'situation', 'image' => ['id' => 9], 'background' => null],
					['key' => 'contact', 'image' => ['id' => 9]],
				],
				'references' => ['items' => [['image' => ['id' => 11]], ['image' => null]]],
			]];

			$this->assertSame([3, 7, 9, 11], MediaRefs::fromSnapshot($snapshot));
			$this->assertSame([], MediaRefs::fromSnapshot(['schema' => 1]));
		}
	}
}
