<?php

namespace App\Guesstimate\Clients;

/**
 * Walidacja i normalizacja polskiego NIP (10 cyfr z sumą kontrolną).
 */
final class Nip
{
	private const WEIGHTS = [6, 5, 7, 2, 3, 4, 5, 6, 7];

	public static function normalize(?string $value): ?string
	{
		if ($value === null) {
			return null;
		}

		$digits = preg_replace('/^PL/i', '', preg_replace('/[\s\-]/', '', trim($value)));

		return $digits === '' ? null : $digits;
	}

	public static function isValid(?string $value): bool
	{
		$nip = self::normalize($value);

		if ($nip === null || ! preg_match('/^\d{10}$/', $nip)) {
			return false;
		}

		$sum = 0;

		foreach (self::WEIGHTS as $i => $weight) {
			$sum += (int) $nip[$i] * $weight;
		}

		$control = $sum % 11;

		return $control !== 10 && $control === (int) $nip[9];
	}
}
