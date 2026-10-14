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
	 * Puste akapity i div-y przeglądarki zamieniamy na akapity.
	 */
	public static function clean(mixed $html): string
	{
		$html = (string) $html;
		$html = preg_replace('#<(/?)div\b[^>]*>#i', '<$1p>', $html);
		$html = preg_replace('#<(/?)h[1-6]\b[^>]*>#i', '<$1p>', $html);
		$html = wp_kses($html, self::ALLOWED, ['http', 'https', 'mailto', 'tel']);
		$html = preg_replace_callback('#<a\b([^>]*)>#i', function ($m) {
			$attrs = preg_replace('#\s(target|rel)="[^"]*"#i', '', $m[1]);

			return '<a' . $attrs . ' target="_blank" rel="noopener noreferrer">';
		}, $html);
		$html = preg_replace('#<p>(\s|&nbsp;|<br\s*/?>)*</p>#i', '', $html);

		return trim($html);
	}
}
