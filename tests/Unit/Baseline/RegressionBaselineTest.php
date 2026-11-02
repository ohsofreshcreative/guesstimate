<?php

namespace {
	if (! function_exists('wp_json_encode')) {
		function wp_json_encode($data, $options = 0) { return json_encode($data, $options); }
	}
}

namespace Tests\Unit\Baseline {

	use App\Guesstimate\Auth\Capabilities;
	use App\Guesstimate\Auth\Roles;
	use App\Guesstimate\Document\DocumentView;
	use App\Guesstimate\Document\Pages;
	use App\Guesstimate\Offers\PublicationService;
	use App\Guesstimate\Offers\TrashService;
	use PHPUnit\Framework\TestCase;

	/**
	 * Testy bazowe z audytu (STAGE-00): kontrakty, na których stoją istniejące oferty, publikacje i role.
	 * Pilnują, żeby biblioteka obrazów, swobodny układ stron i moduł umów tylko dodawały nowe rzeczy.
	 */
	class RegressionBaselineTest extends TestCase
	{
		/** Migracje wykonane w środowiskach — zmiany schematu wyłącznie w nowych plikach. */
		private const APPLIED_MIGRATIONS = [
			'2026_10_13_000001_create_gst_settings_table' => '9b9b058c8c54e6e774b7fb0bf5977fe0c1e9e2871bcdf4cfe4453b5879f086e4',
			'2026_10_13_000002_create_gst_core_tables' => '73e7192e6d70249b2f82d4b4de3c86b3c8faf0388b48f10d18bbc13216517d4f',
			'2026_10_13_000003_seed_gst_defaults' => '466993a9e0fe51881eb5228a106d84377dd71095d58c839a512c9eaa544d3498',
			'2026_10_14_000004_add_gst_v2_columns' => '1cb96feec539b678a6e097ef9a7618709afe46b401f8a7a920ebc962210d4c57',
			'2026_10_14_000005_reduce_gst_feature_library' => '340cd4307163df5a0b30e3909c9768262def788624dac4ddd820a9ce9682ddf1',
			'2026_10_14_000006_create_gst_document_tables' => '329d7e30ccd7605785f4956d2bb43154824f15fda989b17362b1af1b3438da0e',
			'2026_10_14_000007_add_gst_offers_deleted_by' => 'f8562c86aa94951fa587f86c09d24b355eead4b53bea12627ea387c39be445ee',
			'2026_10_15_000008_create_gst_media_refs_table' => 'c7ec2c43573ce647adc58843acd61935a4b493e6fb805c816077a2fa44ba4cad',
			'2026_10_16_000009_create_gst_clickup_oauth_tables' => '47a0cea4f02d02ead75e86ca1a7308bfcebe9a1627c9317efc22060aede36b09',
			'2026_10_17_000010_add_gst_clickup_auto_sync_and_client_deleted_by' => 'e7c874c7cadca32b3929bd44082f5a05029e84a7cf0b1e84f9e10e138a81deaf',
		];

		public function test_applied_migrations_are_not_modified(): void
		{
			foreach (self::APPLIED_MIGRATIONS as $name => $hash) {
				$this->assertSame($hash, hash_file('sha256', dirname(__DIR__, 3) . "/database/migrations/{$name}.php"), $name);
			}
		}

		/**
		 * Odcisk porównuje opublikowany snapshot ze świeżo zbudowanym. Klucz dokładany zawsze (także pusty) oznaczyłby
		 * każdą aktywną publikację jako „nieaktualną”, więc nowe dane strony trafiają do snapshotu tylko, gdy istnieją.
		 */
		public function test_new_page_key_changes_fingerprint_so_it_must_be_omitted_when_unused(): void
		{
			$published = ['schema' => 2, 'document' => ['pages' => [['key' => 'cover', 'layout' => 'full']]]];
			$withEmptyKey = $published;
			$withEmptyKey['document']['pages'][0]['elements'] = [];
			$bumpedSchema = array_replace($published, ['schema' => 3]);

			$this->assertNotSame(PublicationService::fingerprint($published), PublicationService::fingerprint($withEmptyKey));
			$this->assertNotSame(PublicationService::fingerprint($published), PublicationService::fingerprint($bumpedSchema));
		}

		public function test_every_snapshot_generation_keeps_price_content_and_page_order(): void
		{
			$price = ['total' => ['min' => 1000000, 'max' => 1200000, 'text' => '10 000 – 12 000 zł']];
			$content = ['situation' => '<p>Treść</p>', 'blocks' => [['title' => 'Blok', 'html' => '<p>x</p>']]];
			$generations = [
				'bez dokumentu' => ['expected' => ['cover', 'situation', 'solution', 'pricing'], 'document' => null],
				'bez układów' => ['expected' => ['cover', 'pricing'], 'document' => ['pages' => [
					['key' => 'cover', 'label' => 'Okładka', 'background' => null, 'background_id' => null, 'fit' => 'cover'],
					['key' => 'pricing', 'label' => 'Wycena', 'background' => null, 'background_id' => null, 'fit' => 'cover'],
				]]],
				'z układami' => ['expected' => ['cover', 'pricing'], 'document' => ['pages' => [
					['key' => 'cover', 'label' => 'Okładka', 'background' => null, 'background_id' => null, 'fit' => 'cover', 'layout' => 'full', 'image' => null, 'heading' => [], 'hidden' => null],
					['key' => 'pricing', 'label' => 'Wycena', 'background' => null, 'background_id' => null, 'fit' => 'cover', 'layout' => 'full', 'image' => null, 'heading' => [['Cena', true]], 'hidden' => null],
				]]],
			];

			foreach ($generations as $name => $generation) {
				$snapshot = array_filter(['schema' => 2, 'title' => 'T', 'project_type' => 'Sklep', 'price' => $price, 'content' => $content, 'document' => $generation['document']], fn ($v) => $v !== null);
				$view = DocumentView::fromSnapshot($snapshot);

				$this->assertSame($price, $view['price'], $name);
				$this->assertSame($content, $view['content'], $name);
				$this->assertSame($generation['expected'], array_column($view['document']['pages'], 'key'), $name);
			}
		}

		public function test_page_types_and_settings_contract(): void
		{
			$this->assertSame(['cover', 'situation', 'solution', 'pricing', 'extras', 'references', 'next_steps', 'contact'], array_keys(Pages::TYPES));
			$this->assertSame(['enabled', 'background_id', 'fit', 'layout', 'image_id', 'heading'], array_keys(Pages::blank('cover')));
			$this->assertSame(['situation'], Pages::FIT);
		}

		public function test_roles_keep_existing_capabilities(): void
		{
			$manager = [
				Capabilities::ACCESS_APP, Capabilities::CREATE_OFFERS, Capabilities::VIEW_ALL_OFFERS, Capabilities::EDIT_ALL_OFFERS,
				Capabilities::CREATE_CLIENTS, Capabilities::MANAGE_CLIENTS, Capabilities::MANAGE_TRASH,
			];
			$employee = [Capabilities::ACCESS_APP, Capabilities::CREATE_OFFERS, Capabilities::CREATE_CLIENTS];
			$definitions = Roles::definitions();

			$this->assertSame([], array_diff($manager, $definitions[Roles::MANAGER]['capabilities']));
			$this->assertSame([], array_diff($employee, $definitions[Roles::EMPLOYEE]['capabilities']));
			$this->assertContains('upload_files', $definitions[Roles::MANAGER]['wordpress']);
			$this->assertContains('upload_files', $definitions[Roles::EMPLOYEE]['wordpress']);
			$this->assertNotContains(Capabilities::DELETE_OFFERS, $definitions[Roles::MANAGER]['capabilities']);
			$this->assertGreaterThanOrEqual(3, Roles::VERSION);
		}

		public function test_permanent_delete_covers_all_version_tables(): void
		{
			$this->assertSame([], array_diff(
				['gst_v_stages', 'gst_v_templates', 'gst_v_sitemap_nodes', 'gst_v_features', 'gst_v_work_items', 'gst_v_costs'],
				TrashService::VERSION_TABLES,
			));
		}
	}
}
