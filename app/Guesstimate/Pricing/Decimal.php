<?php

namespace App\Guesstimate\Pricing;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Pomocnicze operacje na liczbach dziesiętnych (godziny, procenty). Bez floatów.
 */
final class Decimal
{
	public static function of(string|int $value): BigDecimal
	{
		return BigDecimal::of((string) $value);
	}

	/**
	 * Zaokrągla do wielokrotności kroku (HALF_UP). Krok 0 = bez zaokrąglania (2 miejsca po przecinku).
	 */
	public static function roundToStep(BigDecimal $value, BigDecimal $step): BigDecimal
	{
		if ($step->isZero()) {
			return $value->toScale(2, RoundingMode::HALF_UP);
		}

		return $value->dividedBy($step, 0, RoundingMode::HALF_UP)->multipliedBy($step)->toScale(2, RoundingMode::HALF_UP);
	}

	/**
	 * Godziny × stawka w groszach → grosze (HALF_UP).
	 */
	public static function money(BigDecimal $hours, int $rate): int
	{
		return $hours->multipliedBy($rate)->toScale(0, RoundingMode::HALF_UP)->toInt();
	}

	/**
	 * Kwota zaokrąglona do kroku w groszach (np. 100 = do pełnej złotówki). Krok ≤ 1 = bez zmian.
	 */
	public static function roundMoney(int $amount, int $step): int
	{
		if ($step <= 1) {
			return $amount;
		}

		return BigDecimal::of($amount)->dividedBy($step, 0, RoundingMode::HALF_UP)->toInt() * $step;
	}

	public static function format(BigDecimal $value): string
	{
		return (string) $value->toScale(2, RoundingMode::HALF_UP);
	}
}
