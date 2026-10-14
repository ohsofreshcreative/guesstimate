<?php

namespace App\Guesstimate\Settings;

use App\Guesstimate\Pricing\PricingEngine;
use App\Guesstimate\Pricing\RiskLevel;
use App\Guesstimate\Support\Input;

/**
 * Walidacja trzech stawek godzinowych — te same reguły w ustawieniach i w ofercie:
 * wymagane, > 0, maks. 2 miejsca po przecinku, limit techniczny 100 000 PLN/h, Low ≤ Mid ≤ High.
 */
final class RatesValidator
{
	/**
	 * @param  array<string, mixed>  $input  ['low' => '150', 'mid' => '180,50', 'high' => '200']
	 * @return array{rates: array<string, int>|null, errors: array<string, string>}
	 */
	public static function validate(array $input): array
	{
		$rates = [];
		$errors = [];

		foreach (RiskLevel::cases() as $level) {
			$raw = $input[$level->value] ?? null;
			$normalized = Input::normalizeNumber($raw);

			if ($normalized === null) {
				$errors[$level->value] = sprintf('Podaj stawkę %s.', $level->label());

				continue;
			}

			if (! preg_match('/^-?\d+(\.\d+)?$/', $normalized)) {
				$errors[$level->value] = sprintf('Stawka %s musi być liczbą, np. 180 albo 180,50.', $level->label());

				continue;
			}

			if (preg_match('/\.\d{3,}$/', $normalized)) {
				$errors[$level->value] = sprintf('Stawka %s może mieć najwyżej dwa miejsca po przecinku.', $level->label());

				continue;
			}

			$amount = Input::money($normalized);

			if ($amount === null || $amount <= 0) {
				$errors[$level->value] = sprintf('Stawka %s musi być większa od zera.', $level->label());

				continue;
			}

			if ($amount > PricingEngine::MAX_RATE) {
				$errors[$level->value] = sprintf('Stawka %s nie może przekraczać 100 000 PLN/h.', $level->label());

				continue;
			}

			$rates[$level->value] = $amount;
		}

		if (! $errors) {
			if ($rates['low'] > $rates['mid']) {
				$errors['mid'] = 'Stawka Mid Risk nie może być niższa niż Low Risk.';
			} elseif ($rates['mid'] > $rates['high']) {
				$errors['high'] = 'Stawka High Risk nie może być niższa niż Mid Risk.';
			}
		}

		return ['rates' => $errors ? null : $rates, 'errors' => $errors];
	}
}
