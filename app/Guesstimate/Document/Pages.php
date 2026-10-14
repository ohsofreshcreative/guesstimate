<?php

namespace App\Guesstimate\Document;

/**
 * Typy stron dokumentu oferty (kolejność = kolejność w dokumencie), ich układy i domyślne nagłówki.
 */
final class Pages
{
	public const TYPES = [
		'cover' => 'Okładka',
		'situation' => 'Sytuacja klienta',
		'solution' => 'Nasze rozwiązanie',
		'pricing' => 'Wycena',
		'extras' => 'Usługi dodatkowe',
		'references' => 'Realizacje',
		'next_steps' => 'Kolejne kroki',
		'contact' => 'Kontakt',
	];

	/** Strony, które można wyłączyć dla oferty. */
	public const OPTIONAL = ['extras', 'references', 'next_steps'];

	/** Gotowe układy stron. */
	public const LAYOUTS = [
		'text_image' => 'Tekst po lewej, zdjęcie po prawej',
		'image_text' => 'Zdjęcie po lewej, tekst po prawej',
		'full' => 'Treść na pełną szerokość',
		'tiles' => 'Układ kafelkowy',
	];

	/** Układy dostępne dla typu strony; pierwszy jest domyślny. */
	public const PAGE_LAYOUTS = [
		'cover' => ['full', 'text_image'],
		'situation' => ['text_image', 'image_text', 'full'],
		'solution' => ['full', 'tiles', 'text_image', 'image_text'],
		'pricing' => ['full'],
		'extras' => ['full'], // minimalistyczna tabela usług
		'references' => ['tiles'],
		'next_steps' => ['text_image', 'image_text', 'full'],
		'contact' => ['text_image', 'image_text'],
	];

	/** Układy ze zdjęciem strony obok treści. */
	public const MEDIA_LAYOUTS = ['text_image', 'image_text'];

	/**
	 * Domyślne nagłówki stron (**fragment** = pomarańczowy). Okładka ma własne pole „cover_heading”.
	 * Strona „Wariant cenowy” z domyślnym nagłówkiem dopisuje rodzaj projektu.
	 */
	public const HEADINGS = [
		'situation' => '**Jak rozumiemy Twoją sytuację?**',
		'solution' => '**Jak osiągnąć ten cel?**',
		'pricing' => '**Wariant cenowy –**',
		'extras' => 'Usługi **dodatkowe**',
		'references' => 'W **kooperacji** siła',
		'next_steps' => '**Kolejne kroki**',
		'contact' => 'Zapraszam do kontaktu',
	];

	/** Strony, które zawsze mieszczą się na jednej stronie A4 (dopasowanie typografii zamiast stron „cd.”). */
	public const FIT = ['situation'];

	public const MAX_REFERENCES = 3;
	public const MAX_EXTRAS = 6;
	public const MAX_STEPS = 6;
	public const MAX_INCLUDES = 16;
	public const MAX_HEADING = 160;

	public static function defaultLayout(string $key): string
	{
		return self::PAGE_LAYOUTS[$key][0] ?? 'full';
	}

	public static function layout(string $key, ?string $layout): string
	{
		return in_array($layout, self::PAGE_LAYOUTS[$key] ?? [], true) ? $layout : self::defaultLayout($key);
	}

	public static function hasMedia(string $layout): bool
	{
		return in_array($layout, self::MEDIA_LAYOUTS, true);
	}

	/** Ustawienia strony, gdy wersja ich nie ma (oferty sprzed edytora wizualnego). */
	public static function blank(string $key, ?int $backgroundId = null): array
	{
		return ['enabled' => true, 'background_id' => $backgroundId, 'fit' => null, 'layout' => self::defaultLayout($key), 'image_id' => null, 'heading' => null];
	}
}
