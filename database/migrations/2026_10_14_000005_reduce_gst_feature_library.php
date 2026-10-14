<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MVP v2: domyślna biblioteka funkcjonalności zostaje ograniczona do kilku pozycji.
 * Pozostałe pozycje są dezaktywowane (nie usuwane) — zapisane oferty mają własne kopie i nie zmieniają się.
 */
return new class extends Migration
{
	/**
	 * Kategoria => [nazwa docelowa, [dotychczasowe nazwy], min, max].
	 */
	private const KEEP = [
		'Wielojęzyczność' => [
			['Polylang', ['Polylang'], 6, 10],
			['GTranslate', ['GTranslate'], 2, 4],
		],
		'Marketing' => [
			['Newsletter', ['Newsletter'], 3, 6],
			['Konfiguracja podstawowego SEO', ['Konfiguracja podstawowego SEO', 'Podstawowa konfiguracja SEO'], 3, 6],
		],
		'Użytkownicy' => [
			['Rejestracja i logowanie użytkowników', ['Rejestracja i logowanie użytkowników', 'Rejestracja i logowanie'], 6, 12],
		],
		'Integracje' => [
			['Integracja indywidualna', ['Integracja indywidualna'], 12, 32],
		],
		'Pozostałe' => [
			['Funkcjonalność indywidualna', ['Funkcjonalność indywidualna'], 8, 16],
		],
	];

	public function up(): void
	{
		$now = now();
		$keep = [];
		$sort = (int) DB::table('gst_feature_categories')->max('sort');

		foreach (self::KEEP as $categoryName => $features) {
			$categoryId = DB::table('gst_feature_categories')->where('name', $categoryName)->value('id')
				?? DB::table('gst_feature_categories')->insertGetId(['name' => $categoryName, 'sort' => ++$sort, 'created_at' => $now, 'updated_at' => $now]);

			foreach ($features as $i => [$name, $aliases, $min, $max]) {
				$existing = DB::table('gst_features')->whereIn('name', $aliases)->orderByRaw('is_active desc')->orderBy('id')->first();

				if ($existing) {
					DB::table('gst_features')->where('id', $existing->id)->update([
						'name' => $name, 'category_id' => $categoryId, 'is_active' => true, 'sort' => $i, 'updated_at' => $now,
					]);
					$keep[] = $existing->id;
				} else {
					$keep[] = DB::table('gst_features')->insertGetId([
						'category_id' => $categoryId, 'name' => $name, 'hours_min' => $min, 'hours_max' => $max,
						'stage_key' => 'dev', 'is_active' => true, 'sort' => $i, 'created_at' => $now, 'updated_at' => $now,
					]);
				}
			}
		}

		// Dezaktywujemy wyłącznie pozycje z dawnej domyślnej biblioteki; własne pozycje zespołu zostają.
		DB::table('gst_features')
			->whereNotIn('id', $keep)
			->whereIn('name', self::legacyDefaults())
			->whereNull('created_by')
			->update(['is_active' => false, 'updated_at' => $now]);
	}

	public function down(): void
	{
		// Nie przywracamy automatycznie — aktywność pozycji zmienia się w module Funkcjonalności.
	}

	private static function legacyDefaults(): array
	{
		return [
			'Blog', 'Wyszukiwarka', 'Zaawansowane filtrowanie', 'Custom Post Types',
			'Konfiguracja sklepu', 'Płatności', 'Dostawy', 'InPost', 'Integracja BaseLinker', 'Integracja ERP', 'Konta klientów',
			'Formularze CF7', 'Integracja API', 'Animacje GSAP', 'GA4 i Google Tag Manager',
			'System rezerwacji', 'Migracja danych', 'Import produktów',
		];
	}
};
