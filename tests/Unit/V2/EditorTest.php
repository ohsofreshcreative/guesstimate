<?php

namespace {
	if (! function_exists('wp_json_encode')) {
		function wp_json_encode($data, $options = 0) { return json_encode($data, $options); }
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
				$this->assertArrayHasKey($key, Pages::TYPES);
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
