<?php

namespace App\Guesstimate\Support;

use Illuminate\Contracts\Validation\Validator;

/**
 * Walidator Laravela z polskimi komunikatami (aplikacja nie korzysta z plików językowych Laravela).
 */
final class Validation
{
	public static function make(array $data, array $rules, array $attributes = [], array $messages = []): Validator
	{
		return validator($data, $rules, array_merge(self::messages(), $messages), $attributes);
	}

	private static function messages(): array
	{
		return [
			'required' => 'Pole „:attribute” jest wymagane.',
			'required_with' => 'Pole „:attribute” jest wymagane.',
			'string' => 'Pole „:attribute” musi być tekstem.',
			'max.string' => 'Pole „:attribute” może mieć najwyżej :max znaków.',
			'max.numeric' => 'Pole „:attribute” nie może być większe niż :max.',
			'max.array' => 'Lista „:attribute” może mieć najwyżej :max pozycji.',
			'min.numeric' => 'Pole „:attribute” nie może być mniejsze niż :min.',
			'min.string' => 'Pole „:attribute” musi mieć co najmniej :min znaków.',
			'min.array' => 'Lista „:attribute” musi mieć co najmniej :min pozycji.',
			'email' => 'Podaj prawidłowy adres e-mail.',
			'url' => 'Podaj pełny adres strony, np. https://example.com.',
			'in' => 'Wybierz jedną z dostępnych opcji w polu „:attribute”.',
			'array' => 'Pole „:attribute” ma nieprawidłowy format.',
			'integer' => 'Pole „:attribute” musi być liczbą całkowitą.',
			'numeric' => 'Pole „:attribute” musi być liczbą.',
			'boolean' => 'Pole „:attribute” ma nieprawidłową wartość.',
			'exists' => 'Wybrana wartość pola „:attribute” nie istnieje.',
			'gt' => 'Pole „:attribute” musi być większe od zera.',
			'gte.numeric' => 'Pole „:attribute” nie może być mniejsze niż :value.',
			'lte.numeric' => 'Pole „:attribute” nie może być większe niż :value.',
			'regex' => 'Pole „:attribute” ma nieprawidłowy format.',
			'distinct' => 'Pole „:attribute” zawiera powtórzoną wartość.',
		];
	}
}
