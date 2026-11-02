<?php

namespace App\Guesstimate\Document;

/**
 * Wersjonowany układ pól strony dokumentu (`pages.<klucz>.fields`). Brak układu = układ z szablonu strony,
 * czyli wszystkie dotychczasowe dokumenty i snapshoty.
 *
 * Pole wskazuje istniejący element strony (identyfikator = jego `data-edit`) i przechowuje wyłącznie pozycję.
 * Treść zostaje w swoich polach, więc układ nie może jej zgubić ani powielić. Wyjątkiem jest kopia (`copy_N`):
 * samodzielny tekst powstały przez „Duplikuj”, bez związku z polem źródłowym; usunięcie kopii nie rusza oryginału.
 *
 * Jednostki: procent strony A4 poziomo (297 × 210 mm): x i width szerokości, y i height wysokości. Wysokość
 * jest minimalna: pole rośnie z treścią, a nadmiar obsługuje offer.js.
 */
final class FieldLayout
{
	public const SCHEMA = 1;

	/** Tylko arkusz: sposób pozycjonowania na telefonie czeka na decyzję 1, układ płynny zachowuje szablon strony. */
	public const SHEET = 'sheet';
	public const BREAKPOINTS = [self::SHEET];

	/** Pola, które można pozycjonować. Elementy `data-edit` poza listą (tabele, listy, wycena) zostają w szablonie. */
	public const FIELDS = [
		'cover' => ['cover_lead', 'cover_heading'],
		'situation' => ['heading', 'situation'],
		'solution' => ['heading'],
		'pricing' => ['heading'],
		'extras' => ['heading'],
		'references' => ['heading'],
		'next_steps' => ['heading'],
		'contact' => ['heading'],
	];

	public const LABELS = [
		'heading' => 'Nagłówek',
		'situation' => 'Treść',
		'cover_lead' => 'Opis',
		'cover_heading' => 'Nagłówek okładki',
	];

	private const COPY_ID = '/^copy_[1-9][0-9]{0,2}$/';
	public const MAX_COPIES = 6;
	public const MAX_TEXT = 600;
	/** Wygląd kopii: pochodzi od pola źródłowego. */
	public const ROLES = ['text', 'heading'];

	public const ALIGN = ['left', 'center', 'right'];
	public const MIN_WIDTH = 5.0;
	public const MIN_HEIGHT = 2.0;
	public const MAX_Z = 99;

	/** Zaokrąglenie do 2 miejsc nie może dać błędu zakresu. */
	private const TOLERANCE = 0.01;

	/**
	 * Układ gotowy do zapisu albo null: brak układu, nieznana wersja schematu lub żadne poprawne pole.
	 * Pola są dosuwane do strony, więc zapisany układ nigdy z niej nie wychodzi. Nierozpoznane wpisy są pomijane.
	 *
	 * @return array{schema: int, items: list<array>}|null
	 */
	public static function normalize(mixed $layout, string $page): ?array
	{
		if (! is_array($layout) || (int) ($layout['schema'] ?? 0) !== self::SCHEMA) {
			return null;
		}

		$items = [];
		$copies = 0;

		foreach ((array) ($layout['items'] ?? []) as $item) {
			$field = is_array($item) ? self::item($item, $page) : null;

			if (! $field || (isset($field['text']) && ++$copies > self::MAX_COPIES)) {
				continue;
			}

			$items[$field['id'] . '@' . $field['breakpoint']] = $field;
		}

		return $items ? ['schema' => self::SCHEMA, 'items' => array_values($items)] : null;
	}

	/**
	 * Błędy zakresu układu z formularza. normalize() dosuwa pola do strony, co jest właściwe przy odczycie, ale przy
	 * zapisie po cichu zmieniłoby pozycję: edytor nie wysyła takich wartości, więc odrzucamy je zamiast poprawiać.
	 *
	 * @return list<string>
	 */
	public static function rangeErrors(mixed $layout, string $page): array
	{
		if ($layout === null) {
			return [];
		}

		if (! is_array($layout) || (int) ($layout['schema'] ?? 0) !== self::SCHEMA) {
			return ['nieznana wersja układu pól.'];
		}

		$errors = [];

		foreach ((array) ($layout['items'] ?? []) as $item) {
			$error = self::rangeError($item, $page);

			if ($error) {
				$errors[] = $error;
			}
		}

		return $errors;
	}

	private static function rangeError(mixed $item, string $page): ?string
	{
		$id = is_array($item) ? ($item['id'] ?? null) : null;
		$copy = is_string($id) && preg_match(self::COPY_ID, $id) === 1;

		if (! $copy && ! in_array($id, self::FIELDS[$page] ?? [], true)) {
			return 'nieznane pole układu.';
		}

		$label = $copy ? 'kopia pola' : self::LABELS[$id];
		$x = $item['x'] ?? null;
		$y = $item['y'] ?? null;
		$width = $item['width'] ?? null;
		$height = $item['height'] ?? null;

		foreach ([$x, $y, $width, $height] as $value) {
			if (! is_numeric($value) || ! is_finite((float) $value)) {
				return sprintf('%s: pozycja i rozmiar muszą być liczbami.', $label);
			}
		}

		if ($x < 0 || $y < 0 || $x + $width > 100 + self::TOLERANCE || $y + $height > 100 + self::TOLERANCE) {
			return sprintf('%s: pole wychodzi poza stronę.', $label);
		}

		if ($width < self::MIN_WIDTH - self::TOLERANCE || $height < self::MIN_HEIGHT - self::TOLERANCE) {
			return sprintf('%s: pole jest za małe (min. %s × %s%%).', $label, ImageCrop::css(self::MIN_WIDTH), ImageCrop::css(self::MIN_HEIGHT));
		}

		$z = $item['z_index'] ?? 1;

		return is_numeric($z) && $z >= 1 && $z <= self::MAX_Z ? null : sprintf('%s: warstwa poza zakresem 1–%d.', $label, self::MAX_Z);
	}

	/**
	 * Nagłówek jest jedynym polem strony, więc pod nim płynie treść z szablonu. Po wyjęciu nagłówka z przepływu
	 * treść wskoczyłaby wyżej; ukryty odpowiednik zachowuje jej miejsce.
	 */
	public static function holdsHeadingSpace(array $page): bool
	{
		return (self::FIELDS[$page['key'] ?? ''] ?? []) === ['heading']
			&& in_array('heading', array_column($page['fields']['items'] ?? [], 'id'), true);
	}

	/**
	 * Atrybuty HTML pola. Pusty tekst, gdy pole nie ma pozycji: wtedy HTML strony jest taki jak bez układu.
	 * Wartości pochodzą wyłącznie z normalize(), więc są liczbami lub pozycjami z list.
	 */
	public static function attributes(?array $layout, string $field): string
	{
		foreach ($layout['items'] ?? [] as $item) {
			if ($item['id'] === $field && $item['breakpoint'] === self::SHEET) {
				return sprintf(
					'data-field="%s"%s style="--field-x:%s;--field-y:%s;--field-w:%s;--field-h:%s;--field-z:%d;--field-align:%s"',
					$field,
					$item['constraints']['locked'] ? ' data-locked' : '',
					ImageCrop::css($item['x']),
					ImageCrop::css($item['y']),
					ImageCrop::css($item['width']),
					ImageCrop::css($item['height']),
					$item['z_index'],
					$item['style']['align'],
				);
			}
		}

		return '';
	}

	/**
	 * Pola-kopie strony (mają własny tekst, więc szablon strony ich nie zna).
	 *
	 * @return list<array>
	 */
	public static function copies(?array $layout): array
	{
		return array_values(array_filter($layout['items'] ?? [], fn ($item) => isset($item['text'])));
	}

	private static function item(array $item, string $page): ?array
	{
		$id = $item['id'] ?? null;
		$copy = is_string($id) && preg_match(self::COPY_ID, $id) === 1;
		$text = $copy && is_string($item['text'] ?? null) ? mb_substr(trim(strip_tags($item['text'])), 0, self::MAX_TEXT) : '';
		$breakpoint = $item['breakpoint'] ?? self::SHEET;
		$numbers = [$item['x'] ?? null, $item['y'] ?? null, $item['width'] ?? null, $item['height'] ?? null];

		if (
			(! $copy && ! in_array($id, self::FIELDS[$page] ?? [], true))
			|| ($copy && ($text === '' || ! isset(self::FIELDS[$page])))
			|| ($item['page_id'] ?? $page) !== $page
			|| ! in_array($breakpoint, self::BREAKPOINTS, true)
			|| array_filter($numbers, fn ($value) => ! is_numeric($value) || ! is_finite((float) $value))
		) {
			return null;
		}

		$constraints = (array) ($item['constraints'] ?? []);
		$minWidth = self::bound($constraints['min_width'] ?? null, self::MIN_WIDTH, 100, self::MIN_WIDTH);
		$minHeight = self::bound($constraints['min_height'] ?? null, self::MIN_HEIGHT, 100, self::MIN_HEIGHT);
		$width = self::bound($numbers[2], $minWidth, 100, $minWidth);
		$height = self::bound($numbers[3], $minHeight, 100, $minHeight);
		$align = $item['style']['align'] ?? null;
		$role = $item['style']['role'] ?? null;

		return [
			'id' => $id,
			'page_id' => $page,
			'breakpoint' => $breakpoint,
			'x' => self::bound($numbers[0], 0, 100 - $width, 0),
			'y' => self::bound($numbers[1], 0, 100 - $height, 0),
			'width' => $width,
			'height' => $height,
			'z_index' => (int) self::bound($item['z_index'] ?? null, 1, self::MAX_Z, 1),
			'style' => ['align' => in_array($align, self::ALIGN, true) ? $align : 'left'] + ($copy ? ['role' => in_array($role, self::ROLES, true) ? $role : 'text'] : []),
			'constraints' => ['locked' => ! empty($constraints['locked']), 'min_width' => $minWidth, 'min_height' => $minHeight],
		] + ($copy ? ['text' => $text] : []);
	}

	private static function bound(mixed $value, float $min, float $max, float $default): float
	{
		$number = is_numeric($value) && is_finite((float) $value) ? (float) $value : $default;

		return round(max($min, min($max, $number)), 2);
	}
}
