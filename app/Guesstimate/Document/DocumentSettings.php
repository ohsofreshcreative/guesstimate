<?php

namespace App\Guesstimate\Document;

use App\Guesstimate\Offers\OfferVersion;

/**
 * Ustawienia dokumentu wersji oferty. Nowa oferta dostaje kopię ustawień z panelu (w tym domyślne tła);
 * od tej chwili należą one do oferty — zmiana ustawień globalnych nie zmienia istniejących ofert.
 */
final class DocumentSettings
{
	/**
	 * @param  array  $settings  ustawienia globalne (SettingsRepository::all())
	 */
	public static function initial(array $settings, ?int $contactUserId = null): array
	{
		$doc = (array) ($settings['document'] ?? []);
		$backgrounds = (array) ($doc['backgrounds'] ?? []);
		$pages = [];

		foreach (array_keys(Pages::TYPES) as $key) {
			$pages[$key] = Pages::blank($key, isset($backgrounds[$key]) && $backgrounds[$key] ? (int) $backgrounds[$key] : null);
		}

		return [
			'cover_heading' => (string) ($doc['cover_heading'] ?? ''),
			'cover_lead' => (string) ($doc['cover_lead'] ?? ''),
			'price_includes' => array_values((array) ($doc['price_includes'] ?? [])),
			'include_features' => true,
			'extras_intro' => (string) ($doc['extras_intro'] ?? ''),
			'extras' => array_values((array) ($doc['extras'] ?? [])),
			'references_intro' => (string) ($doc['references_intro'] ?? ''),
			'reference_ids' => CaseStudy::where('is_active', true)->orderBy('sort')->orderBy('id')->limit(Pages::MAX_REFERENCES)->pluck('id')->all(),
			'next_steps' => array_values((array) ($doc['next_steps'] ?? [])),
			'next_steps_note' => (string) ($doc['next_steps_note'] ?? ''),
			'contact_user_id' => $contactUserId,
			'pages' => $pages,
			// Wynik pomiaru z edytora (czy strona „Sytuacja” mieści się na A4) — tylko metadane, nie treść.
			'fit_report' => [],
		];
	}

	/**
	 * Ustawienia dokumentu wersji; starsze wersje bez ustawień dostają wartości startowe (bez zapisu).
	 */
	public static function forVersion(OfferVersion $version, array $settings): array
	{
		$initial = self::initial($settings, $version->offer?->author_id);
		$stored = (array) ($version->document ?? []);

		if (! $stored) {
			return $initial;
		}

		$merged = array_replace($initial, $stored);
		$pages = [];

		// Strony zapisane przed edytorem wizualnym nie mają układu, zdjęcia ani nagłówka — dostają wartości domyślne
		// (zapisane tło zostaje). Kolejność stron zawsze z Pages::TYPES (MySQL sortuje klucze JSON).
		foreach (array_keys(Pages::TYPES) as $key) {
			$storedPage = (array) ($stored['pages'][$key] ?? []);
			$page = $storedPage ? array_replace(Pages::blank($key), $storedPage) : $initial['pages'][$key];
			$page['layout'] = Pages::layout($key, $page['layout'] ?? null);
			$pages[$key] = $page;
		}

		$merged['pages'] = $pages;
		$merged['fit_report'] = (array) ($stored['fit_report'] ?? []);

		return $merged;
	}

	/**
	 * Skrót stanu, od którego zależy dopasowanie strony „Sytuacja” (treść, nagłówek, układ, zdjęcie).
	 * Pomiar z edytora jest ważny tylko dla tego samego stanu.
	 */
	public static function fitHash(array $doc, array $content, string $key = 'situation'): string
	{
		$page = $doc['pages'][$key] ?? [];

		return md5(wp_json_encode([
			$content['situation'] ?? '',
			$page['heading'] ?? null,
			Pages::layout($key, $page['layout'] ?? null),
			$page['image_id'] ?? null,
		]));
	}

	/**
	 * Ostateczne tło strony: tło wybrane w ofercie albo brak (wtedy styl domyślny dokumentu).
	 */
	public static function fit(array $page, ?Background $background): string
	{
		return $page['fit'] ?? $background?->fit ?? 'cover';
	}
}
