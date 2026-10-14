<?php

namespace App\Guesstimate\Support;

use App\Guesstimate\Pricing\Decimal;
use Brick\Math\RoundingMode;
use DateTimeInterface;

/**
 * Formatowanie wartości do wyświetlenia (polskie separatory, strefa czasowa WordPressa).
 */
final class Format
{
	private const NBSP = "\u{00A0}";

	/**
	 * Grosze → „12 800 zł” albo „1 994,13 zł”.
	 */
	public static function money(?int $amount, string $currency = 'PLN'): string
	{
		if ($amount === null) {
			return '—';
		}

		$symbol = $currency === 'PLN' ? 'zł' : $currency;
		$decimals = $amount % 100 === 0 ? 0 : 2;

		return number_format($amount / 100, $decimals, ',', self::NBSP) . self::NBSP . $symbol;
	}

	/**
	 * Zakres kwot „7 200–12 800 zł”.
	 */
	public static function moneyRange(?int $min, ?int $max, string $currency = 'PLN'): string
	{
		if ($min === null || $max === null) {
			return '—';
		}

		if ($min === $max) {
			return self::money($min, $currency);
		}

		$symbol = $currency === 'PLN' ? 'zł' : $currency;

		return self::number($min) . '–' . self::number($max) . self::NBSP . $symbol;
	}

	private static function number(int $amount): string
	{
		return number_format($amount / 100, $amount % 100 === 0 ? 0 : 2, ',', self::NBSP);
	}

	/**
	 * Stawka w groszach → „180 zł/h”.
	 */
	public static function rate(int $amount, string $currency = 'PLN'): string
	{
		return self::money($amount, $currency) . '/h';
	}

	/**
	 * Godziny „48.00” → „48 h”, „12.50” → „12,5 h”.
	 */
	public static function hours(string|int|float|null $hours, bool $unit = true): string
	{
		if ($hours === null || $hours === '') {
			return '—';
		}

		$value = Decimal::of((string) $hours)->toScale(2, RoundingMode::HALF_UP);
		$text = rtrim(rtrim(str_replace('.', ',', (string) $value), '0'), ',');

		return $unit ? $text . self::NBSP . 'h' : $text;
	}

	public static function hoursRange(?array $range): string
	{
		if (! $range) {
			return '—';
		}

		if (Decimal::of($range['min'])->isEqualTo($range['max'])) {
			return self::hours($range['min']);
		}

		return self::hours($range['min'], false) . '–' . self::hours($range['max']);
	}

	/**
	 * Jednostki kalkulacyjne rodzaju projektu: „+8 jedn. kalkulacyjnych” / „bez dodatkowych jednostek”.
	 */
	public static function units(string|int|float|null $units): string
	{
		if ($units === null || $units === '' || ! Decimal::of((string) $units)->isPositive()) {
			return 'bez dodatkowych jednostek';
		}

		return '+' . self::hours($units, false) . ' jedn. kalkulacyjnych';
	}

	/**
	 * Wartość do pola formularza: godziny bez zbędnych zer („4,5”).
	 */
	public static function hoursInput(string|int|float|null $hours): string
	{
		return $hours === null || $hours === '' ? '' : self::hours($hours, false);
	}

	/**
	 * Grosze do pola formularza („150” albo „150,50”).
	 */
	public static function moneyInput(?int $amount): string
	{
		if ($amount === null) {
			return '';
		}

		return $amount % 100 === 0 ? (string) intdiv($amount, 100) : number_format($amount / 100, 2, ',', '');
	}

	public static function date(DateTimeInterface|string|null $date, bool $withTime = false): string
	{
		if (! $date) {
			return '—';
		}

		$timestamp = $date instanceof DateTimeInterface ? $date->getTimestamp() : strtotime($date . ' UTC');

		return wp_date($withTime ? 'j.m.Y, H:i' : 'j.m.Y', $timestamp);
	}

	public static function percent(string|int|null $value): string
	{
		return $value === null ? '—' : self::hours($value, false) . self::NBSP . '%';
	}
}
