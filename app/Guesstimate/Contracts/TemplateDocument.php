<?php

namespace App\Guesstimate\Contracts;

/**
 * Układ treści szablonu: nagłówek, treść i stopka w jednej kolumnie `body`. Dzięki temu należą do tych samych
 * bajtów co `content_hash` i przechodzą do umowy razem z tekstem. Wcześniejsza treść bez sekcji to sama treść główna.
 */
final class TemplateDocument
{
	public const REGIONS = ['header', 'body', 'footer'];

	/** Limit bajtów jednej sekcji: wielokrotność najdłuższej rozsądnej umowy, a nie jej oczekiwana długość. */
	public const MAX_BYTES = 2_000_000;

	public static function compose(string $header, string $body, string $footer): string
	{
		$out = '';

		foreach (['header' => $header, 'body' => $body, 'footer' => $footer] as $region => $html) {
			$out .= '<section data-region="' . $region . '">' . $html . "</section>\n";
		}

		return $out;
	}

	/**
	 * @return array{header: string, body: string, footer: string}
	 */
	public static function parse(string $stored): array
	{
		$pattern = '#^<section data-region="header">(.*)</section>\n<section data-region="body">(.*)</section>\n<section data-region="footer">(.*)</section>\n$#s';

		if (preg_match($pattern, $stored, $parts) === 1) {
			return ['header' => $parts[1], 'body' => $parts[2], 'footer' => $parts[3]];
		}

		return ['header' => '', 'body' => $stored, 'footer' => ''];
	}

	public static function isBlank(string $stored): bool
	{
		return ContractHtml::text(self::parse($stored)['body']) === '';
	}
}
