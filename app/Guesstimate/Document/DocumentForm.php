<?php

namespace App\Guesstimate\Document;

use App\Guesstimate\Offers\OfferContent;
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

		// Tylko gdy formularz zna tę opcję: krok 5 kreatora jej nie wysyła i nie może jej skasować.
		if (array_key_exists('layout_mode', $input)) {
			$mode = $input['layout_mode'];
			unset($data['layout_mode']);

			if ($mode === DocumentSettings::MODE_TEMPLATE) {
				$data['layout_mode'] = $mode;
			} elseif ($mode !== null && $mode !== '') {
				$errors['layout_mode'] = 'Nieznany tryb układu.';
			}
		}

		return ['data' => $errors ? null : $data, 'draft' => $data, 'errors' => $errors];
	}

	/**
	 * Wygląd stron z edytora wizualnego: układ, tło, dopasowanie tła, zdjęcie strony, nagłówek, widoczność,
	 * a także strony dodatkowe (własne i kopie) i kolejność stron. Nieznane klucze są pomijane; brakujące pola
	 * zostają bez zmian. Strona dodatkowa spoza podanej kolejności jest usuwana (bez kolejności nic nie znika).
	 *
	 * @return array{data: array|null, draft: array, errors: array<string, string>}
	 */
	public static function design(array $input, array $current, mixed $order = null): array
	{
		$errors = [];
		$pages = $current['pages'];

		if ($order !== null && ! is_array($order)) {
			$errors['page_order'] = 'Nieprawidłowa kolejność stron.';
			$order = null;
		}

		if ($order !== null) {
			foreach (array_keys($pages) as $id) {
				if (Pages::isExtra((string) $id) && ! in_array((string) $id, $order, true)) {
					unset($pages[$id]);
				}
			}
		}

		foreach ($input as $id => $row) {
			$id = (string) $id;

			if (! Pages::isExtra($id) || isset($pages[$id]) || ! is_array($row) || ($order !== null && ! in_array($id, $order, true))) {
				continue;
			}

			if (! Pages::extraType($row['type'] ?? null)) {
				$errors["pages.{$id}.type"] = 'Nieznany typ strony.';
				continue;
			}

			if (count(array_filter(array_keys($pages), fn ($key) => Pages::isExtra((string) $key))) >= Pages::MAX_EXTRA) {
				$errors['page_order'] = sprintf('Najwyżej %d stron dodatkowych.', Pages::MAX_EXTRA);
				continue;
			}

			$pages[$id] = ['type' => $row['type']] + Pages::blank($row['type']) + ($row['type'] === Pages::CUSTOM ? ['html' => ''] : []);
		}

		foreach ($pages as $key => $page) {
			$key = (string) $key;

			if (! isset($input[$key]) || ! is_array($input[$key])) {
				continue;
			}

			$type = Pages::type($key, $page);
			$extra = Pages::isExtra($key);
			$label = Pages::label($key, $page);
			$row = $input[$key];

			if (array_key_exists('layout', $row)) {
				if (in_array($row['layout'], Pages::PAGE_LAYOUTS[$type], true)) {
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
					// Kadr dotyczy konkretnego zdjęcia: przy zmianie wraca do domyślnego, chyba że formularz podał nowy.
					if ($id !== ($page['image_id'] ?? null)) {
						$page['image_crop'] = null;
					}

					$page['image_id'] = $id;
				}
			}

			if (array_key_exists('image_crop', $row)) {
				if ($error = ImageCrop::rangeError($row['image_crop'])) {
					$errors["pages.{$key}.image_crop"] = sprintf('%s: %s', $label, $error);
				}

				$page['image_crop'] = empty($page['image_id']) ? null : ImageCrop::normalize($row['image_crop']);
			}

			// Strony dodatkowe mają układ z szablonu (DocumentSettings::activeFields).
			if (array_key_exists('fields', $row) && ! $extra) {
				if ($problems = FieldLayout::rangeErrors($row['fields'], $key)) {
					$errors["pages.{$key}.fields"] = sprintf('%s: %s', $label, $problems[0]);
				}

				$page['fields'] = FieldLayout::normalize($row['fields'], $key);
			}

			if (array_key_exists('polish', $row)) {
				if ($error = PagePolish::rangeError($row['polish'])) {
					$errors["pages.{$key}.polish"] = sprintf('%s: %s', $label, $error);
				}

				$page['polish'] = PagePolish::normalize($row['polish'], $type, array_map('intval', (array) ($current['reference_ids'] ?? [])));
			}

			if (array_key_exists('heading', $row) && $type !== 'cover') {
				$heading = mb_substr((string) (Input::line($row['heading']) ?? ''), 0, Pages::MAX_HEADING);
				// Pusty nagłówek = nagłówek domyślny strony (własna strona nie ma domyślnego).
				$page['heading'] = $heading === '' || $heading === (Pages::HEADINGS[$type] ?? null) ? null : $heading;
			}

			if (array_key_exists('html', $row) && $type === Pages::CUSTOM) {
				$html = OfferContent::clean($row['html']);

				if (mb_strlen($html) > Pages::MAX_CUSTOM_HTML) {
					$errors["pages.{$key}.html"] = sprintf('%s: treść jest za długa.', $label);
					$html = mb_substr($html, 0, Pages::MAX_CUSTOM_HTML);
				}

				$page['html'] = $html;
			}

			if (array_key_exists('enabled', $row)) {
				if (! $extra && ! in_array($key, Pages::OPTIONAL, true) && ! $row['enabled']) {
					$errors["pages.{$key}.enabled"] = sprintf('Strony „%s” nie można wyłączyć.', $label);
				} else {
					$page['enabled'] = (bool) $row['enabled'];
				}
			}

			$pages[$key] = $page;
		}

		$data = array_replace($current, ['pages' => $pages, 'page_order' => Pages::order($pages, $order ?? ($current['page_order'] ?? null))]);

		return ['data' => $errors ? null : $data, 'draft' => $data, 'errors' => $errors];
	}
}
