<?php

namespace App\Guesstimate\Pricing;

/**
 * Walidacja stawek godzinowych wspólna dla obu silników (grosze, Low ≤ Mid ≤ High).
 */
final class Rates
{
	public const MAX = 10_000_000; // 100 000 PLN/h w groszach (limit techniczny)

	/**
	 * @param  array<string, mixed>  $rates
	 */
	public static function assert(array $rates): void
	{
		foreach (RiskLevel::cases() as $level) {
			$rate = $rates[$level->value] ?? null;

			if (! is_int($rate) || $rate <= 0 || $rate > self::MAX) {
				throw new PricingException(sprintf('Stawka %s musi być większa od zera i nie większa niż 100 000 PLN/h.', $level->label()));
			}
		}

		if ($rates['low'] > $rates['mid'] || $rates['mid'] > $rates['high']) {
			throw new PricingException('Stawki muszą zachować kolejność Low ≤ Mid ≤ High.');
		}
	}
}
