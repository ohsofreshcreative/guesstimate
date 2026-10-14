<?php

namespace App\Guesstimate\Library;

use App\Guesstimate\Pricing\Decimal;
use App\Guesstimate\Settings\Defaults;
use App\Guesstimate\Support\Input;

/**
 * Walidacja funkcjonalności biblioteki i pozycji godzinowych (wspólne reguły widełek godzin).
 */
class FeatureService
{
	/**
	 * @return array{data: array|null, errors: array<string, string>}
	 */
	public function validate(array $input, bool $requireCategory = true): array
	{
		$errors = [];
		$name = Input::line($input['name'] ?? null);

		if ($name === null) {
			$errors['name'] = 'Podaj nazwę funkcjonalności.';
		} elseif (mb_strlen($name) > 190) {
			$errors['name'] = 'Nazwa może mieć najwyżej 190 znaków.';
		}

		$categoryId = (int) ($input['category_id'] ?? 0);

		if ($requireCategory && ! FeatureCategory::whereKey($categoryId)->exists()) {
			$errors['category_id'] = 'Wybierz kategorię.';
		}

		[$min, $max, $hoursErrors] = self::hours($input['hours_min'] ?? null, $input['hours_max'] ?? null);
		$errors = array_merge($errors, $hoursErrors);

		$stage = (string) ($input['stage_key'] ?? 'dev');

		if (! in_array($stage, Defaults::STAGE_KEYS, true)) {
			$errors['stage_key'] = 'Wybierz etap projektu.';
		}

		$data = [
			'name' => $name,
			'category_id' => $categoryId ?: null,
			'description' => Input::text($input['description'] ?? null),
			'hours_min' => $min,
			'hours_max' => $max,
			'stage_key' => $stage,
			'is_active' => filter_var($input['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN),
		];

		return ['data' => $errors ? null : $data, 'errors' => $errors];
	}

	/**
	 * Wspólna walidacja widełek godzin [min, max].
	 *
	 * @return array{0: string|null, 1: string|null, 2: array<string, string>}
	 */
	public static function hours(mixed $min, mixed $max, string $minKey = 'hours_min', string $maxKey = 'hours_max'): array
	{
		$errors = [];
		$minValue = Input::hours($min);
		$maxValue = Input::hours($max);

		if ($minValue === null) {
			$errors[$minKey] = 'Podaj minimalną liczbę godzin (np. 4 albo 4,5).';
		}

		if ($maxValue === null) {
			$errors[$maxKey] = 'Podaj maksymalną liczbę godzin (np. 6 albo 6,5).';
		}

		if ($minValue !== null && $maxValue !== null && Decimal::of($minValue)->isGreaterThan($maxValue)) {
			$errors[$maxKey] = 'Maksimum godzin nie może być mniejsze niż minimum.';
		}

		return [$minValue, $maxValue, $errors];
	}
}
