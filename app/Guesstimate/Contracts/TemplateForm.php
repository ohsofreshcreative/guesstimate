<?php

namespace App\Guesstimate\Contracts;

/**
 * Dane z formularzy szablonu. Tu, a nie w usłudze, jest czyszczenie: usługa zapisuje to, co dostała, co do bajtu.
 */
final class TemplateForm
{
	/**
	 * @return array{name: string, description: ?string, errors: array<string, string>}
	 */
	public static function meta(array $input): array
	{
		$name = trim((string) ($input['name'] ?? ''));
		$description = trim((string) ($input['description'] ?? ''));
		$errors = [];

		if ($name === '') {
			$errors['name'] = 'Podaj nazwę szablonu.';
		} elseif (mb_strlen($name) > 190) {
			$errors['name'] = 'Nazwa może mieć najwyżej 190 znaków.';
		}

		if (mb_strlen($description) > 500) {
			$errors['description'] = 'Opis może mieć najwyżej 500 znaków.';
		}

		return ['name' => $name, 'description' => $description === '' ? null : $description, 'errors' => $errors];
	}

	/**
	 * @return array{body: string, errors: array<string, string>}
	 */
	public static function document(array $input): array
	{
		$regions = [];
		$errors = [];

		foreach (TemplateDocument::REGIONS as $region) {
			$raw = $input[$region] ?? '';

			if (! is_string($raw) || strlen($raw) > TemplateDocument::MAX_BYTES) {
				$errors[$region] = 'Ta część dokumentu jest za długa.';
				$raw = '';
			}

			$regions[$region] = ContractHtml::clean($raw);
		}

		return ['body' => TemplateDocument::compose($regions['header'], $regions['body'], $regions['footer']), 'errors' => $errors];
	}
}
