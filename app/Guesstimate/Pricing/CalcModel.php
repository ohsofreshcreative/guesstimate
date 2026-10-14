<?php

namespace App\Guesstimate\Pricing;

enum CalcModel: string
{
	/** Model B: każdy etap = suma godzin jego pozycji × stawka. */
	case Hourly = 'hourly';

	/** Model A (zgodny z calc.xlsx): suma godzin × stawka, minus kwoty stałe, reszta dzielona procentami. */
	case Percent = 'percent';

	public function label(): string
	{
		return match ($this) {
			self::Hourly => 'Godzinowy',
			self::Percent => 'Procentowy (jak w Excelu)',
		};
	}
}
