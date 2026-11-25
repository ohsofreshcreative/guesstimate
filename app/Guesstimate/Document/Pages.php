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

	/**
	 * Strony dodatkowe (`extra_N`): własna strona z treścią z edytora albo kopia strony gotowego typu (te same dane,
	 * własny wygląd). Typ zapisuje się w `pages.<id>.type`, kolejność wszystkich stron w `document.page_order`.
	 */
	public const CUSTOM = 'custom';
	public const CUSTOM_LABEL = 'Własna strona';

	/**
	 * Szablony własnych stron. Każda ma nagłówek, wstęp (WYSIWYG) i — poza tekstem — własne pozycje w `items`,
	 * niezależne od danych oferty.
	 */
	public const CUSTOM_TYPES = [
		self::CUSTOM => 'Tekst',
		'custom_table' => 'Tabela',
		'custom_tiles' => 'Kafelki',
		'custom_steps' => 'Lista kroków',
	];

	/** Pola i limity pozycji własnych stron (nazwa pola => maks. długość). */
	public const CUSTOM_ITEMS = [
		'custom_table' => ['max' => 12, 'fields' => ['name' => 120, 'description' => 600, 'price' => 120], 'required' => 'name'],
		'custom_tiles' => ['max' => 6, 'fields' => ['title' => 120, 'text' => 600], 'required' => 'title'],
		'custom_steps' => ['max' => 8, 'fields' => ['title' => 140, 'note' => 140], 'required' => 'title'],
	];
	public const MAX_EXTRA = 12;
	public const MAX_CUSTOM_HTML = 20000;
	private const EXTRA_ID = '/^extra_[1-9][0-9]{0,2}$/';
	/** Bez kopii: dopasowanie typografii „Sytuacji” mierzy jedną stronę. */
	public const NO_COPY = ['situation'];

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
		self::CUSTOM => ['full', 'text_image', 'image_text'],
		'custom_table' => ['full'],
		'custom_tiles' => ['tiles', 'full'],
		'custom_steps' => ['text_image', 'image_text', 'full'],
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

	public static function isExtra(string $id): bool
	{
		return preg_match(self::EXTRA_ID, $id) === 1;
	}

	/** Typ, który może mieć strona dodatkowa. */
	public static function extraType(mixed $type): bool
	{
		return self::isCustom($type) || (is_string($type) && isset(self::TYPES[$type]) && ! in_array($type, self::NO_COPY, true));
	}

	/** Własna strona (dowolny szablon): treść należy do strony, a nie do danych oferty. */
	public static function isCustom(mixed $type): bool
	{
		return is_string($type) && isset(self::CUSTOM_TYPES[$type]);
	}

	/** Pusta własna strona danego szablonu. */
	public static function blankCustom(string $type): array
	{
		return ['type' => $type] + self::blank($type) + ['html' => ''] + (isset(self::CUSTOM_ITEMS[$type]) ? ['items' => []] : []);
	}

	/** Typ strony: klucz strony gotowej albo typ zapisany w stronie dodatkowej. */
	public static function type(string $id, array $page = []): string
	{
		return isset(self::TYPES[$id]) ? $id : (string) ($page['type'] ?? self::CUSTOM);
	}

	public static function label(string $id, array $page = []): string
	{
		if (isset(self::TYPES[$id])) {
			return self::TYPES[$id];
		}

		$type = self::type($id, $page);

		if (self::isCustom($type)) {
			$heading = trim(str_replace('**', '', (string) ($page['heading'] ?? '')));

			return $heading !== '' ? mb_substr($heading, 0, 60) : ($type === self::CUSTOM ? self::CUSTOM_LABEL : self::CUSTOM_TYPES[$type]);
		}

		return (self::TYPES[$type] ?? self::CUSTOM_LABEL) . ' (kopia)';
	}

	/**
	 * Kolejność stron: zapisana kolejność bez nieznanych i powtórzonych kluczy, a brakujące strony na końcu
	 * (gotowe w kolejności TYPES, dodatkowe według numeru — MySQL sortuje klucze JSON po swojemu).
	 *
	 * @return list<string>
	 */
	public static function order(array $pages, mixed $order): array
	{
		$out = [];

		foreach ((array) $order as $id) {
			if (is_string($id) && isset($pages[$id]) && ! in_array($id, $out, true)) {
				$out[] = $id;
			}
		}

		$extras = array_values(array_filter(array_map('strval', array_keys($pages)), fn ($id) => self::isExtra($id)));
		usort($extras, fn ($a, $b) => (int) substr($a, 6) <=> (int) substr($b, 6));

		foreach ([...array_keys(self::TYPES), ...$extras] as $id) {
			if (isset($pages[$id]) && ! in_array($id, $out, true)) {
				$out[] = $id;
			}
		}

		return $out;
	}
}
