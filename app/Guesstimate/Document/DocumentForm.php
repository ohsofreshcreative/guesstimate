<?php

namespace App\Guesstimate\Document;

use App\Guesstimate\Support\Input;

/**
 * Walidacja ustawień dokumentu: treści stron dodatkowych (krok 5) i wyglądu stron (krok 6).
 */
final class DocumentForm
{
	/**
	 * Teksty stron dokumentu (krok 5 i edytor wizualny). „draft” to oczyszczone dane także przy błędach —
	 * na potrzeby podglądu na żywo (bez zapisu).
	 *
	 * @return array{data: array|null, draft: array, errors: array<string, string>}
	 */
	public static function content(array $input, array $current): array
	{
		$errors = [];
		$text = fn ($v, $max) => mb_substr((string) (Input::text($v) ?? ''), 0, $max);
		$line = fn ($v, $max) => mb_substr((string) (Input::line($v) ?? ''), 0, $max);

		$heading = $line($input['cover_heading'] ?? '', 160);

		if ($heading === '') {
			$errors['cover_heading'] = 'Podaj nagłówek okładki.';
		}

		$includes = array_values(array_filter(array_map(fn ($l) => mb_substr(trim($l), 0, 190), preg_split('/\R/', (string) ($input['price_includes'] ?? ''))), fn ($l) => $l !== ''));

		if (count($includes) > Pages::MAX_INCLUDES) {
			$errors['price_includes'] = sprintf('Lista „Cena zawiera” może mieć najwyżej %d pozycji.', Pages::MAX_INCLUDES);
		}

		$extras = [];

		foreach (array_values((array) ($input['extras'] ?? [])) as $i => $row) {
			$name = $line($row['name'] ?? '', 120);
			$description = $text($row['description'] ?? '', 600);
			$price = $line($row['price'] ?? '', 120);

			if ($name === '' && $description === '' && $price === '') {
				continue;
			}

			if ($name === '') {
				$errors["extras.{$i}.name"] = sprintf('Usługa %d: podaj nazwę.', $i + 1);
			}

			$extras[] = ['name' => $name, 'description' => $description, 'price' => $price];
		}

		if (count($extras) > Pages::MAX_EXTRAS) {
			$errors['extras'] = sprintf('Najwyżej %d usług dodatkowych.', Pages::MAX_EXTRAS);
		}

		$steps = [];

		foreach (array_values((array) ($input['next_steps'] ?? [])) as $i => $row) {
			$title = $line($row['title'] ?? '', 140);
			$note = $line($row['note'] ?? '', 140);

			if ($title === '' && $note === '') {
				continue;
			}

			if ($title === '') {
				$errors["next_steps.{$i}.title"] = sprintf('Krok %d: podaj treść.', $i + 1);
			}

			$steps[] = ['title' => $title, 'note' => $note];
		}

		if (count($steps) > Pages::MAX_STEPS) {
			$errors['next_steps'] = sprintf('Najwyżej %d kroków.', Pages::MAX_STEPS);
		}

		$referenceIds = array_values(array_unique(array_map('intval', (array) ($input['reference_ids'] ?? []))));
		$referenceIds = CaseStudy::whereIn('id', $referenceIds ?: [0])->where('is_active', true)->orderByRaw('FIELD(id, ' . implode(',', $referenceIds ?: [0]) . ')')->pluck('id')->all();

		if (count($referenceIds) > Pages::MAX_REFERENCES) {
			$errors['reference_ids'] = sprintf('Wybierz najwyżej %d realizacje.', Pages::MAX_REFERENCES);
		}

		$contactId = (int) ($input['contact_user_id'] ?? 0);

		if ($contactId && ! array_key_exists($contactId, Contacts::team())) {
			$errors['contact_user_id'] = 'Wybierz osobę z zespołu.';
		}

		$data = array_replace($current, [
			'cover_heading' => $heading,
			'cover_lead' => $text($input['cover_lead'] ?? '', 400),
			'price_includes' => $includes,
			'include_features' => ! empty($input['include_features']),
			'extras_intro' => $text($input['extras_intro'] ?? '', 800),
			'extras' => $extras,
			'references_intro' => $text($input['references_intro'] ?? '', 600),
			'reference_ids' => $referenceIds,
			'next_steps' => $steps,
			'next_steps_note' => $text($input['next_steps_note'] ?? '', 300),
			'contact_user_id' => $contactId ?: null,
		]);

		return ['data' => $errors ? null : $data, 'draft' => $data, 'errors' => $errors];
	}

	/**
	 * Wygląd stron z edytora wizualnego: układ, tło, dopasowanie tła, zdjęcie strony, nagłówek, widoczność.
	 * Klucze spoza Pages::TYPES są pomijane; brakujące pola zostają bez zmian.
	 *
	 * @return array{data: array|null, draft: array, errors: array<string, string>}
	 */
	public static function design(array $input, array $current): array
	{
		$errors = [];
		$pages = $current['pages'];

		foreach (Pages::TYPES as $key => $label) {
			if (! isset($input[$key]) || ! is_array($input[$key])) {
				continue;
			}

			$row = $input[$key];
			$page = $pages[$key];

			if (array_key_exists('layout', $row)) {
				if (in_array($row['layout'], Pages::PAGE_LAYOUTS[$key], true)) {
					$page['layout'] = $row['layout'];
				} else {
					$errors["pages.{$key}.layout"] = sprintf('%s: ten układ nie jest dostępny dla tej strony.', $label);
				}
			}

			if (array_key_exists('background_id', $row)) {
				$id = $row['background_id'] === null || $row['background_id'] === '' ? null : (int) $row['background_id'];

				if ($id !== null && ! Background::whereKey($id)->exists()) {
					$errors["pages.{$key}.background_id"] = sprintf('%s: tego tła nie ma w bibliotece.', $label);
				} else {
					$page['background_id'] = $id;
				}
			}

			if (array_key_exists('fit', $row)) {
				$fit = $row['fit'] === null || $row['fit'] === '' ? null : (string) $row['fit'];

				if ($fit !== null && ! array_key_exists($fit, Background::FITS)) {
					$errors["pages.{$key}.fit"] = 'Nieznany sposób dopasowania tła.';
				} else {
					$page['fit'] = $fit;
				}
			}

			if (array_key_exists('image_id', $row)) {
				$id = $row['image_id'] === null || $row['image_id'] === '' ? null : (int) $row['image_id'];

				// Zdjęcie, które zniknęło z biblioteki, pozwalamy usunąć ze strony, ale nie wybrać ponownie.
				if ($id !== null && $id !== ($pages[$key]['image_id'] ?? null) && ! Media::isImage($id)) {
					$errors["pages.{$key}.image_id"] = sprintf('%s: wybierz obraz JPG, PNG lub WebP z biblioteki mediów.', $label);
				} else {
					$page['image_id'] = $id;
				}
			}

			if (array_key_exists('heading', $row) && $key !== 'cover') {
				$heading = mb_substr((string) (Input::line($row['heading']) ?? ''), 0, Pages::MAX_HEADING);
				// Pusty nagłówek = nagłówek domyślny strony.
				$page['heading'] = $heading === '' || $heading === Pages::HEADINGS[$key] ? null : $heading;
			}

			if (array_key_exists('enabled', $row)) {
				if (! in_array($key, Pages::OPTIONAL, true) && ! $row['enabled']) {
					$errors["pages.{$key}.enabled"] = sprintf('Strony „%s” nie można wyłączyć.', $label);
				} else {
					$page['enabled'] = (bool) $row['enabled'];
				}
			}

			$pages[$key] = $page;
		}

		$data = array_replace($current, ['pages' => $pages]);

		return ['data' => $errors ? null : $data, 'draft' => $data, 'errors' => $errors];
	}
}
