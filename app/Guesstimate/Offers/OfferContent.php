<?php

namespace App\Guesstimate\Offers;

use App\Guesstimate\Support\Input;

/**
 * Treści oferty (krok 5): „Jak rozumiemy Twoją sytuację?” i 3–5 bloków „Jak osiągnąć ten cel?”.
 * HTML jest zawsze czyszczony po stronie serwera do bezpiecznego podzbioru (akapity, pogrubienie,
 * kursywa, listy, linki).
 */
final class OfferContent
{
	public const MIN_BLOCKS = 3;
	public const MAX_BLOCKS = 5;
	public const MAX_HTML = 20000;

	public const ALLOWED = [
		'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [],
		'ul' => [], 'ol' => [], 'li' => [],
		'a' => ['href' => true, 'title' => true, 'target' => true, 'rel' => true],
	];

	/** Nagłówki tylko w treści własnej strony dokumentu: H1/H2 stają się H3, a H6 staje się H5. */
	public const HEADINGS = ['h3' => ['class' => true], 'h4' => ['class' => true], 'h5' => ['class' => true]];

	/** Kolory tekstu własnej strony: paleta dokumentu (offer.css), zapisywana jako klasa `osf-c-<nazwa>`. */
	public const COLORS = ['indigo', 'magenta', 'violet', 'flame', 'orange'];

	public static function blank(): array
	{
		return [
			'situation' => '',
			'blocks' => array_fill(0, self::MIN_BLOCKS, ['title' => '', 'html' => '']),
		];
	}

	public static function normalize(?array $content): array
	{
		$content ??= self::blank();
		$blocks = array_values(array_map(fn ($b) => ['title' => (string) ($b['title'] ?? ''), 'html' => (string) ($b['html'] ?? '')], (array) ($content['blocks'] ?? [])));

		while (count($blocks) < self::MIN_BLOCKS) {
			$blocks[] = ['title' => '', 'html' => ''];
		}

		return ['situation' => (string) ($content['situation'] ?? ''), 'blocks' => array_slice($blocks, 0, self::MAX_BLOCKS)];
	}

	/**
	 * @return array{data: array|null, errors: array<string, string>}
	 */
	public static function validate(array $input): array
	{
		$errors = [];
		$situation = self::clean($input['situation'] ?? '');

		if (mb_strlen($situation) > self::MAX_HTML) {
			$errors['situation'] = 'Tekst jest za długi.';
		}

		$blocks = [];

		foreach (array_values((array) ($input['blocks'] ?? [])) as $i => $block) {
			$title = Input::line($block['title'] ?? null) ?? '';
			$html = self::clean($block['html'] ?? '');

			if (mb_strlen($title) > 190) {
				$errors["blocks.{$i}.title"] = 'Nagłówek może mieć do 190 znaków.';
			}

			if (mb_strlen($html) > self::MAX_HTML) {
				$errors["blocks.{$i}.html"] = 'Tekst jest za długi.';
			}

			$blocks[] = ['title' => $title, 'html' => $html];
		}

		// Całkiem puste bloki ponad minimum pomijamy (np. dodany i niewypełniony).
		while (count($blocks) > self::MIN_BLOCKS && ($last = end($blocks)) && $last['title'] === '' && self::isEmpty($last['html'])) {
			array_pop($blocks);
		}

		if (count($blocks) < self::MIN_BLOCKS || count($blocks) > self::MAX_BLOCKS) {
			$errors['blocks'] = sprintf('Sekcja „Jak osiągnąć ten cel?” musi mieć od %d do %d bloków.', self::MIN_BLOCKS, self::MAX_BLOCKS);
		}

		return ['data' => $errors ? null : ['situation' => $situation, 'blocks' => $blocks], 'errors' => $errors];
	}

	/**
	 * Braki, które blokują publikację (szkic można zapisać niepełny).
	 *
	 * @return list<string>
	 */
	public static function missing(?array $content): array
	{
		$content = self::normalize($content);
		$missing = [];

		if (self::isEmpty($content['situation'])) {
			$missing[] = 'Uzupełnij sekcję „Jak rozumiemy Twoją sytuację?”.';
		}

		$filled = array_filter($content['blocks'], fn ($b) => $b['title'] !== '' && ! self::isEmpty($b['html']));

		if (count($filled) < self::MIN_BLOCKS || count($filled) !== count($content['blocks'])) {
			$missing[] = sprintf('Każdy blok „Jak osiągnąć ten cel?” (min. %d) musi mieć nagłówek i treść.', self::MIN_BLOCKS);
		}

		return $missing;
	}

	public static function isEmpty(string $html): bool
	{
		return trim(html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \n\r\t\u{00A0}") === '';
	}

	/**
	 * Czyści HTML z edytora: tylko dozwolone znaczniki, linki http(s)/mailto/tel, linki zewnętrzne z rel="noopener".
	 * Puste akapity i div-y przeglądarki zamieniamy na akapity. Z `$headings` zostają nagłówki H3–H5.
	 */
	/**
	 * Zostawia wyłącznie klasy kolorów z palety; span bez koloru znika (zostaje jego tekst).
	 */
	private static function colors(string $html): string
	{
		$html = (string) preg_replace_callback('#\sclass="([^"]*)"#i', function ($match) {
			$keep = array_values(array_intersect(preg_split('/\s+/', trim($match[1])) ?: [], array_map(fn ($color) => 'osf-c-' . $color, self::COLORS)));

			return $keep ? ' class="' . $keep[0] . '"' : '';
		}, $html);

		return (string) preg_replace('#<span>(.*?)</span>#is', '$1', $html);
	}

	public static function clean(mixed $html, bool $headings = false): string
	{
		$html = (string) $html;
		$html = preg_replace('#<(/?)div\b[^>]*>#i', '<$1p>', $html);

		if ($headings) {
			$html = preg_replace(['#<(/?)h[12]\b[^>]*>#i', '#<(/?)h6\b[^>]*>#i'], ['<$1h3>', '<$1h5>'], $html);
		} else {
			$html = preg_replace('#<(/?)h[1-6]\b[^>]*>#i', '<$1p>', $html);
		}

		$allowed = $headings ? array_merge(self::ALLOWED, self::HEADINGS, ['span' => ['class' => true], 'p' => ['class' => true], 'li' => ['class' => true]]) : self::ALLOWED;
		$html = wp_kses($html, $allowed, ['http', 'https', 'mailto', 'tel']);

		if ($headings) {
			$html = self::colors($html);
		}
		$html = preg_replace_callback('#<a\b([^>]*)>#i', function ($m) {
			$attrs = preg_replace('#\s(target|rel)="[^"]*"#i', '', $m[1]);

			return '<a' . $attrs . ' target="_blank" rel="noopener noreferrer">';
		}, $html);
		$html = preg_replace('#<(p|h[345])>(\s|&nbsp;|<br\s*/?>)*</\1>#i', '', $html);

		return trim($html);
	}
}
