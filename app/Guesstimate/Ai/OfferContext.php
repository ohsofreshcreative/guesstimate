<?php

namespace App\Guesstimate\Ai;

use App\Guesstimate\Offers\OfferVersion;

/**
 * Kontekst oferty przekazywany do AI. Celowo bez danych osobowych (osoba kontaktowa, e-mail, telefon, NIP),
 * bez cen, stawek i notatek wewnętrznych — tylko to, czego model potrzebuje do opisu zakresu.
 */
final class OfferContext
{
	public static function of(OfferVersion $version): array
	{
		$version->loadMissing(['nodes', 'features', 'workItems', 'offer.client']);
		$client = $version->offer?->client;

		return array_filter([
			'firma_klienta' => $client?->company_name,
			'strona_klienta' => $client?->website_url,
			'nazwa_projektu' => $version->project_name,
			'rodzaj_projektu' => $version->projectTypeLabel(),
			'zakres_ux' => $version->ux_label,
			'mapa_strony' => $version->nodes->sortBy('position')->take(80)->pluck('name')->values()->all(),
			'funkcjonalnosci' => $version->features->take(40)->map(fn ($f) => array_filter([
				'nazwa' => $f->name,
				'opis' => $f->description ? mb_substr($f->description, 0, 400) : null,
			]))->values()->all(),
			'prace_dodatkowe' => $version->workItems->take(30)->pluck('name')->values()->all(),
		], fn ($v) => $v !== null && $v !== []);
	}

	public static function json(OfferVersion $version): string
	{
		return wp_json_encode(self::of($version), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
	}
}
