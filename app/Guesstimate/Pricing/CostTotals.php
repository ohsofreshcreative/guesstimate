<?php

namespace App\Guesstimate\Pricing;

/**
 * Sumy kosztów niezwiązanych z godzinami: jednorazowe, miesięczne, roczne i roczny koszt cykliczny.
 */
final class CostTotals
{
	/**
	 * @param  list<Cost>  $costs
	 * @return array{one_time: int, monthly: int, yearly: int, annual_recurring: int}
	 */
	public static function of(array $costs): array
	{
		$totals = ['one_time' => 0, 'monthly' => 0, 'yearly' => 0];

		foreach ($costs as $cost) {
			if (! isset($totals[$cost->billing])) {
				throw new PricingException(sprintf('Nieznany typ rozliczenia kosztu „%s”.', $cost->name));
			}

			if ($cost->amount < 0 || ! PricingEngine::isHours($cost->quantity)) {
				throw new PricingException(sprintf('Koszt „%s” nie może być ujemny.', $cost->name));
			}

			$totals[$cost->billing] += Decimal::money(Decimal::of($cost->quantity), $cost->amount);
		}

		$totals['annual_recurring'] = $totals['monthly'] * 12 + $totals['yearly'];

		return $totals;
	}
}
