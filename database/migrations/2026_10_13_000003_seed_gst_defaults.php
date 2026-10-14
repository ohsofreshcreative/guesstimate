<?php

use App\Guesstimate\Settings\Defaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dane startowe: ustawienia, typy podstron i przykładowa biblioteka funkcjonalności.
 * Wstawia tylko brakujące dane — nigdy nie nadpisuje ani nie usuwa istniejących.
 */
return new class extends Migration
{
	public function up(): void
	{
		$now = now();

		foreach (Defaults::all() as $key => $value) {
			DB::table('gst_settings')->insertOrIgnore([
				'key' => $key,
				'value' => json_encode($value, JSON_UNESCAPED_UNICODE),
				'created_at' => $now,
				'updated_at' => $now,
			]);
		}

		if (DB::table('gst_page_types')->count() === 0) {
			foreach (Defaults::pageTypes() as $sort => [$name, $kind, $uiMin, $uiMax, $devMin, $devMax, $cMin, $cMax]) {
				DB::table('gst_page_types')->insert([
					'name' => $name, 'kind' => $kind,
					'ui_min' => $uiMin, 'ui_max' => $uiMax, 'dev_min' => $devMin, 'dev_max' => $devMax,
					'content_min' => $cMin, 'content_max' => $cMax,
					'is_active' => true, 'sort' => $sort, 'created_at' => $now, 'updated_at' => $now,
				]);
			}
		}

		if (DB::table('gst_feature_categories')->count() === 0) {
			$sort = 0;

			foreach (Defaults::features() as $category => $features) {
				$categoryId = DB::table('gst_feature_categories')->insertGetId([
					'name' => $category, 'sort' => $sort++, 'created_at' => $now, 'updated_at' => $now,
				]);

				foreach ($features as $i => [$name, $min, $max, $stage]) {
					DB::table('gst_features')->insert([
						'category_id' => $categoryId, 'name' => $name, 'hours_min' => $min, 'hours_max' => $max,
						'stage_key' => $stage, 'is_active' => true, 'sort' => $i, 'created_at' => $now, 'updated_at' => $now,
					]);
				}
			}
		}
	}

	public function down(): void
	{
		// Dane startowe nie są usuwane przy wycofaniu migracji.
	}
};
