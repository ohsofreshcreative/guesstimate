<?php

namespace App\Guesstimate\Support;

use App\Guesstimate\Pricing\Decimal;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;

/**
 * Normalizacja danych z formularzy (polski przecinek, spacje tysięcy).
 * Zwraca null, gdy wartości nie da się jednoznacznie odczytać — walidacja zgłasza wtedy błąd.
 */
final class Input
{
	public static function normalizeNumber(mixed $value): ?string
	{
		if ($value === null) {
			return null;
		}

		$value = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim((string) $value));

		return $value === '' ? null : $value;
	}

	/**
	 * „150,50” → 15050 groszy. Maksymalnie 2 miejsca po przecinku, bez wartości ujemnych.
	 */
	public static function money(mixed $value): ?int
	{
		$value = self::normalizeNumber($value);

		if ($value === null || ! preg_match('/^\d{1,9}(\.\d{1,2})?$/', $value)) {
			return null;
		}

		return BigDecimal::of($value)->multipliedBy(100)->toScale(0, RoundingMode::UNNECESSARY)->toInt();
	}

	/**
	 * „4,5” → „4.50”. Maksymalnie 2 miejsca po przecinku.
	 */
	public static function hours(mixed $value): ?string
	{
		$value = self::normalizeNumber($value);

		if ($value === null || ! preg_match('/^\d{1,6}(\.\d{1,2})?$/', $value)) {
			return null;
		}

		return Decimal::format(Decimal::of($value));
	}

	public static function decimal(mixed $value, int $maxDecimals = 2): ?string
	{
		$value = self::normalizeNumber($value);

		if ($value === null || ! preg_match('/^\d{1,9}(\.\d{1,' . $maxDecimals . '})?$/', $value)) {
			return null;
		}

		try {
			return (string) BigDecimal::of($value)->toScale($maxDecimals, RoundingMode::UNNECESSARY);
		} catch (MathException) {
			return null;
		}
	}

	public static function text(mixed $value): ?string
	{
		if ($value === null) {
			return null;
		}

		$value = trim(sanitize_textarea_field((string) $value));

		return $value === '' ? null : $value;
	}

	public static function line(mixed $value): ?string
	{
		if ($value === null) {
			return null;
		}

		$value = trim(sanitize_text_field((string) $value));

		return $value === '' ? null : $value;
	}
}
