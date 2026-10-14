<?php

namespace App\Guesstimate\Pricing;

/**
 * Etap projektu w kalkulacji.
 *
 * Źródło wartości etapu jest zawsze jedno:
 * - kwota stała, gdy $fixedAmount !== null,
 * - procent z puli (tylko model procentowy, gdy $inPool),
 * - suma godzin pozycji przypisanych do etapu.
 */
final class Stage
{
	public const SOURCE_FIXED = 'fixed';
	public const SOURCE_PERCENT = 'percent';
	public const SOURCE_HOURS = 'hours';

	/**
	 * @param  string  $percent  udział w puli, np. '20' (tylko model procentowy)
	 * @param  int|null  $fixedAmount  kwota stała w groszach
	 */
	public function __construct(
		public readonly string $key,
		public readonly string $name,
		public readonly bool $enabled = true,
		public readonly bool $inPool = false,
		public readonly string $percent = '0',
		public readonly ?int $fixedAmount = null,
	) {
	}

	public function source(CalcModel $model): string
	{
		if ($this->fixedAmount !== null) {
			return self::SOURCE_FIXED;
		}

		if ($model === CalcModel::Percent && $this->inPool) {
			return self::SOURCE_PERCENT;
		}

		return self::SOURCE_HOURS;
	}
}
