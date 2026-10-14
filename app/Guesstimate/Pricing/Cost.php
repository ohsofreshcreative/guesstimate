<?php

namespace App\Guesstimate\Pricing;

/**
 * Koszt niezwiązany z godzinami pracy (licencja, wtyczka, hosting…).
 * Koszty jednorazowe wchodzą do ceny realizacji, cykliczne są liczone osobno.
 */
final class Cost
{
	public const ONE_TIME = 'one_time';
	public const MONTHLY = 'monthly';
	public const YEARLY = 'yearly';

	/**
	 * @param  int  $amount  cena jednostkowa netto w groszach
	 * @param  string  $quantity  ilość (dziesiętna)
	 */
	public function __construct(
		public readonly string $name,
		public readonly int $amount,
		public readonly string $quantity = '1',
		public readonly string $billing = self::ONE_TIME,
	) {
	}
}
