<?php

namespace App\Guesstimate\Pricing;

/**
 * Poziom ryzyka = własna stawka godzinowa. Godziny są takie same we wszystkich wariantach.
 */
enum RiskLevel: string
{
	case Low = 'low';
	case Mid = 'mid';
	case High = 'high';

	public function label(): string
	{
		return match ($this) {
			self::Low => 'Low Risk',
			self::Mid => 'Mid Risk',
			self::High => 'High Risk',
		};
	}
}
