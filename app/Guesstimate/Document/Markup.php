<?php

namespace App\Guesstimate\Document;

/**
 * Proste wyróżnienia w krótkich tekstach dokumentu: **tekst** = pomarańczowy akcent.
 * Zwraca fragmenty [tekst, wyróżniony] — widok sam je escapuje, więc nie ma tu HTML-a.
 */
final class Markup
{
	/**
	 * @return list<array{0: string, 1: bool}>
	 */
	public static function parts(?string $text): array
	{
		$text = (string) $text;

		if ($text === '') {
			return [];
		}

		$parts = [];

		foreach (preg_split('/(\*\*[^*]+\*\*)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $chunk) {
			$highlight = str_starts_with($chunk, '**') && str_ends_with($chunk, '**') && mb_strlen($chunk) > 4;
			$parts[] = [$highlight ? mb_substr($chunk, 2, -2) : $chunk, $highlight];
		}

		return $parts;
	}

	/**
	 * Fragmenty jako bezpieczny HTML (escapowany tekst, wyróżnienia w <span>).
	 *
	 * @param  list<array{0: string, 1: bool}>  $parts
	 */
	public static function html(array $parts, string $class = 'text-orange'): string
	{
		return implode('', array_map(
			fn ($p) => $p[1] ? '<span class="' . esc_attr($class) . '">' . esc_html($p[0]) . '</span>' : esc_html($p[0]),
			$parts
		));
	}
}
