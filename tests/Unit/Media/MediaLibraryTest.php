<?php

namespace {
	if (! function_exists('sanitize_text_field')) {
		function sanitize_text_field($text) { return strip_tags((string) $text); }
	}
}

namespace Tests\Unit\Media {

	use App\Guesstimate\Media\MediaAttributes;
	use App\Guesstimate\Media\MediaLibrary;
	use App\Guesstimate\Media\MediaUsage;
	use PHPUnit\Framework\TestCase;

	/**
	 * Model biblioteki grafik: walidacja opisu, filtry zapytań i ocena użycia (bez WordPressa i bazy).
	 */
	class MediaLibraryTest extends TestCase
	{
		public function test_full_description_is_normalized(): void
		{
			$result = MediaAttributes::validate([
				'title' => ' Biuro ', 'alt' => 'Zdjęcie biura', 'author' => 'Jan', 'license' => 'stock',
				'source_url' => 'https://example.com/a.jpg', 'folder' => '7', 'tags' => 'wnętrza, Biuro, biuro ,',
			]);

			$this->assertSame([], $result['errors']);
			$this->assertSame('Biuro', $result['data']['title']);
			$this->assertSame(7, $result['data']['folder']);
			$this->assertSame(['wnętrza', 'Biuro'], $result['data']['tags']);
		}

		public function test_only_title_is_required(): void
		{
			$result = MediaAttributes::validate(['title' => 'Logo']);

			$this->assertSame([], $result['errors']);
			$this->assertNull($result['data']['alt']);
			$this->assertNull($result['data']['license']);
			$this->assertNull($result['data']['folder']);
			$this->assertSame([], $result['data']['tags']);
		}

		public function test_invalid_description_is_rejected(): void
		{
			$result = MediaAttributes::validate([
				'title' => '', 'license' => 'gpl', 'source_url' => 'javascript:alert(1)',
				'alt' => str_repeat('a', 256), 'tags' => array_map(fn ($i) => "t{$i}", range(1, 21)),
			]);

			$this->assertNull($result['data']);
			$this->assertEqualsCanonicalizing(['title', 'license', 'source_url', 'alt', 'tags'], array_keys($result['errors']));
		}

		public function test_default_query_hides_archived_images(): void
		{
			$this->assertSame(['meta_query' => [MediaLibrary::visible()]], MediaLibrary::queryArgs());
			$this->assertSame('EXISTS', MediaLibrary::queryArgs(['archived' => true])['meta_query'][0]['compare']);
		}

		public function test_folder_and_tags_filters(): void
		{
			$args = MediaLibrary::queryArgs(['folder' => 3, 'tags' => ['4', 5]]);

			$this->assertSame(MediaLibrary::FOLDER, $args['tax_query'][0]['taxonomy']);
			$this->assertSame([3], $args['tax_query'][0]['terms']);
			$this->assertSame([4, 5], $args['tax_query'][1]['terms']);
			$this->assertSame('AND', $args['tax_query'][1]['operator']);
		}

		public function test_every_relation_counts_as_use(): void
		{
			$this->assertFalse(MediaUsage::inUse(MediaUsage::NONE));

			foreach (['published' => ['OSF/1'], 'versions' => ['OSF/1'], 'backgrounds' => ['Tło'], 'cases' => ['Britannia'], 'logo' => true] as $key => $value) {
				$this->assertTrue(MediaUsage::inUse([$key => $value]), $key);
			}
		}

		public function test_usage_summary(): void
		{
			$this->assertSame('', MediaUsage::summary(MediaUsage::NONE));
			$this->assertSame(
				'używają go: opublikowane oferty OSF/1; tła Tło; logo dokumentu',
				MediaUsage::summary(['published' => ['OSF/1'], 'backgrounds' => ['Tło'], 'logo' => true]),
			);
			$this->assertStringContainsString('i 2 więcej', MediaUsage::summary(['versions' => ['1', '2', '3', '4', '5', '6', '7']]));
		}
	}
}
