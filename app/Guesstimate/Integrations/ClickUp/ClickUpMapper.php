<?php

namespace App\Guesstimate\Integrations\ClickUp;

/**
 * Zamienia zadanie ClickUp na dane klienta Guesstimate według mapowania pól z ustawień.
 * Źródło pola: 'name' (nazwa zadania), 'cf:<id pola niestandardowego>' albo '' (nie importuj).
 */
final class ClickUpMapper
{
	public const TARGETS = [
		'company_name' => 'Nazwa firmy',
		'website_url' => 'Strona WWW',
		'contact_name' => 'Osoba kontaktowa',
		'email' => 'E-mail',
		'phone' => 'Telefon',
		'nip' => 'NIP',
	];

	/**
	 * @param  array<string, string>  $mapping  pole docelowe => źródło
	 * @return array<string, string|null>
	 */
	public static function map(array $task, array $mapping): array
	{
		$fields = [];

		foreach ($task['custom_fields'] ?? [] as $field) {
			$fields[(string) ($field['id'] ?? '')] = $field;
		}

		$out = [];

		foreach (array_keys(self::TARGETS) as $target) {
			$source = (string) ($mapping[$target] ?? ($target === 'company_name' ? 'name' : ''));
			$value = match (true) {
				$source === 'name' => (string) ($task['name'] ?? ''),
				str_starts_with($source, 'cf:') => self::fieldValue($fields[substr($source, 3)] ?? null),
				default => null,
			};

			$value = $value === null ? null : trim(wp_strip_all_tags($value));
			$out[$target] = ($value === null || $value === '') ? null : mb_substr($value, 0, $target === 'phone' ? 50 : 190);
		}

		return $out;
	}

	/**
	 * Wartość pola niestandardowego jako tekst (tekst, e-mail, telefon, URL, liczba, lista rozwijana, lokalizacja).
	 */
	public static function fieldValue(?array $field): ?string
	{
		if (! $field || ! array_key_exists('value', $field) || $field['value'] === null || $field['value'] === '') {
			return null;
		}

		$value = $field['value'];

		return match ($field['type'] ?? '') {
			'drop_down' => self::option($field, $value),
			'location' => is_array($value) ? (string) ($value['formatted_address'] ?? '') : null,
			'labels', 'users', 'tasks', 'attachment' => null,
			default => is_scalar($value) ? (string) $value : null,
		};
	}

	private static function option(array $field, mixed $value): ?string
	{
		foreach ($field['type_config']['options'] ?? [] as $option) {
			if ((string) ($option['id'] ?? '') === (string) $value || (isset($option['orderindex']) && (string) $option['orderindex'] === (string) $value)) {
				return (string) ($option['name'] ?? '');
			}
		}

		return null;
	}
}
