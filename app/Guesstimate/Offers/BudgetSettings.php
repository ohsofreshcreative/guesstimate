<?php

namespace App\Guesstimate\Offers;

/**
 * Parametry modelu budżetowego kopiowane z ustawień do oferty (i od tej pory należące do oferty).
 */
final class BudgetSettings
{
	/**
	 * Fragment ustawień zapisywany w settings_snapshot nowej wersji.
	 */
	public static function snapshot(array $settings): array
	{
		return [
			'rounding' => self::rounding($settings['rounding'] ?? []),
			'vat_rate' => $settings['vat_rate'],
			'ux_scopes' => $settings['ux_scopes'],
			'hourly_rates' => $settings['hourly_rates'],
			'project_types' => $settings['project_types'],
			'page_hours' => $settings['page_hours'],
			'workshops_amount' => (int) $settings['workshops_amount'],
			'budget_split' => $settings['budget_split'],
		];
	}

	/**
	 * Parametry kalkulacji wersji dla wybranego rodzaju projektu.
	 * Przy zmianie rodzaju zachowujemy parametry ustawione już w ofercie (godziny na podstronę, warsztaty, podział).
	 */
	public static function budget(array $snapshot, string $type, ?array $current = null, ?string $customUnits = null): array
	{
		$types = $snapshot['project_types'] ?? [];
		$typeChanged = ($current['project_type'] ?? null) !== $type;

		// „Inny”: liczba jednostek wpisana w ofercie (domyślnie 0), a nie wartość z ustawień.
		if ($type === OfferVersion::CUSTOM_UNITS_TYPE) {
			$units = $customUnits ?? ($typeChanged ? '0' : (string) ($current['type_units'] ?? '0'));
		} else {
			$units = $typeChanged || ! isset($current['type_units']) ? (string) ($types[$type]['units'] ?? '0') : (string) $current['type_units'];
		}

		return [
			'project_type' => $type,
			'project_type_label' => $types[$type]['label'] ?? (OfferVersion::PROJECT_TYPES[$type] ?? $type),
			'type_units' => $units,
			'page_hours' => $current['page_hours'] ?? $snapshot['page_hours'],
			'workshops' => (int) ($current['workshops'] ?? $snapshot['workshops_amount'] ?? 0),
			'split' => $current['split'] ?? $snapshot['budget_split'],
			'part_step' => (int) ($current['part_step'] ?? $snapshot['rounding']['part_step'] ?? 100),
		];
	}

	public static function rounding(array $rounding): array
	{
		return [
			'hours_step' => (string) ($rounding['hours_step'] ?? '0.25'),
			'price_step' => (int) ($rounding['price_step'] ?? 100),
			'part_step' => (int) ($rounding['part_step'] ?? 100),
		];
	}

	/**
	 * Rodzaje projektu do wyboru (etykiety i jednostki z ustawień) w stałej kolejności SELECTABLE_TYPES.
	 * $keep dokłada wycofany rodzaj, jeśli edytowana oferta już go ma (np. „Aplikacja webowa”).
	 *
	 * @return array<string, array{label: string, units: string}>
	 */
	public static function types(array $settingsOrSnapshot, ?string $keep = null): array
	{
		$stored = (array) ($settingsOrSnapshot['project_types'] ?? []);
		$keys = OfferVersion::SELECTABLE_TYPES;

		if ($keep !== null && array_key_exists($keep, OfferVersion::PROJECT_TYPES) && ! in_array($keep, $keys, true)) {
			$keys[] = $keep;
		}

		$types = [];

		foreach ($keys as $key) {
			$types[$key] = [
				'label' => (string) ($stored[$key]['label'] ?? OfferVersion::PROJECT_TYPES[$key]),
				'units' => $key === OfferVersion::CUSTOM_UNITS_TYPE ? '0' : (string) ($stored[$key]['units'] ?? '0'),
			];
		}

		return $types;
	}

	/**
	 * Zakresy UX w stałej kolejności: najpierw Zoptymalizowany, potem Pełny (niezależnie od kolejności w JSON).
	 *
	 * @return array<string, array{label: string, min: string, max: string}>
	 */
	public static function uxScopes(array $scopes): array
	{
		$order = ['optimized', 'full'];
		uksort($scopes, fn ($a, $b) => (array_search($a, $order, true) === false ? 99 : array_search($a, $order, true)) <=> (array_search($b, $order, true) === false ? 99 : array_search($b, $order, true)));

		return $scopes;
	}
}
