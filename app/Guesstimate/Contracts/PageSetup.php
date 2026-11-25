<?php

namespace App\Guesstimate\Contracts;

use App\Guesstimate\Settings\SettingsRepository;

/**
 * Układ strony umowy jak w Wordzie (ustawienia, sekcja „Układ strony umów”): marginesy oraz odległość nagłówka
 * i stopki od krawędzi kartki, w mm. Treść zaczyna się na marginesie, a gdy nagłówek jest wyższy — pod nim
 * (z odstępem GAP), więc nagłówek, stopka i tło nigdy nie nachodzą na treść. To wygląd, nie treść umowy:
 * nie wchodzi do skrótu i dotyczy podglądu, PDF i DOCX wszystkich umów.
 */
final class PageSetup
{
	public const FIELDS = [
		'top' => 'Margines górny',
		'bottom' => 'Margines dolny',
		'left' => 'Margines lewy',
		'right' => 'Margines prawy',
		'header' => 'Nagłówek od górnej krawędzi',
		'footer' => 'Stopka od dolnej krawędzi',
	];

	/** Dotychczasowy układ: 20 mm wszędzie, nagłówek i stopka na marginesie. */
	public const DEFAULTS = ['top' => 20, 'bottom' => 20, 'left' => 20, 'right' => 20, 'header' => 20, 'footer' => 20];

	/** Odstęp między nagłówkiem lub stopką a treścią, w mm. */
	public const GAP = 3;

	/** Limity w mm: lewy i prawy margines razem muszą zostawić miejsce na tekst, a górny i dolny — na treść strony. */
	public const MAX = ['top' => 120, 'bottom' => 120, 'left' => 60, 'right' => 60, 'header' => 100, 'footer' => 100];

	/** @param array<string, float> $values */
	private function __construct(public readonly array $values)
	{
	}

	public static function defaults(): self
	{
		return new self(array_map('floatval', self::DEFAULTS));
	}

	public static function current(): self
	{
		return self::from((array) app(SettingsRepository::class)->get('contract_page'));
	}

	/** Wartości z ustawień; brakujące lub błędne zastępuje układ domyślny. */
	public static function from(array $stored): self
	{
		$values = [];

		foreach (self::DEFAULTS as $key => $default) {
			$value = $stored[$key] ?? null;
			$values[$key] = is_numeric($value) && $value >= 0 && $value <= self::MAX[$key] ? round((float) $value, 1) : (float) $default;
		}

		return new self($values);
	}

	/**
	 * @return array{values: array<string, float>, errors: array<string, string>}
	 */
	public static function validate(array $input): array
	{
		$values = [];
		$errors = [];

		foreach (self::FIELDS as $key => $label) {
			$raw = str_replace(',', '.', trim((string) ($input[$key] ?? '')));

			if (! is_numeric($raw) || (float) $raw < 0 || (float) $raw > self::MAX[$key]) {
				$errors[$key] = sprintf('Podaj liczbę od 0 do %d mm.', self::MAX[$key]);

				continue;
			}

			$values[$key] = round((float) $raw, 1);
		}

		return ['values' => $values, 'errors' => $errors];
	}

	public function get(string $key): float
	{
		return $this->values[$key];
	}

	/** Szerokość pola tekstu w mm. */
	public function width(): float
	{
		return 210 - $this->values['left'] - $this->values['right'];
	}

	/**
	 * Początek treści od górnej krawędzi (mm). $header to wysokość nagłówka razem z GAP (0 = bez nagłówka).
	 */
	public function bodyTop(float $header): float
	{
		return $header > 0 ? max($this->values['top'], $this->values['header'] + $header) : $this->values['top'];
	}

	/** Koniec treści od dolnej krawędzi (mm). $footer to wysokość stopki razem z GAP (0 = bez stopki). */
	public function bodyBottom(float $footer): float
	{
		return $footer > 0 ? max($this->values['bottom'], $this->values['footer'] + $footer) : $this->values['bottom'];
	}

	/** Zmienne CSS kartki w panelu (.ct-sheet, .ct-paper). */
	public function css(): string
	{
		$css = '';

		foreach ($this->values as $key => $value) {
			$css .= sprintf('--ct-%s: %smm; ', $key, rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.'));
		}

		return trim($css);
	}
}
