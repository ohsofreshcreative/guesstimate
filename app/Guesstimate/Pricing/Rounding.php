<?php

namespace App\Guesstimate\Pricing;

final class Rounding
{
	/**
	 * @param  string  $hoursStep  krok zaokrąglenia godzin, np. '0.25'; '0' = bez zaokrąglania
	 * @param  int  $priceStep  krok zaokrąglenia ceny końcowej w groszach, np. 100 = 1 zł; 1 = bez zaokrąglania
	 * @param  int  $partStep  krok zaokrąglenia kwot podziału UX / UI (model budżetowy); Development przejmuje resztę
	 */
	public function __construct(
		public readonly string $hoursStep = '0',
		public readonly int $priceStep = 1,
		public readonly int $partStep = 1,
	) {
	}
}
