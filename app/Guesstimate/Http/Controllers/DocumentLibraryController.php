<?php

namespace App\Guesstimate\Http\Controllers;

use App\Guesstimate\Document\Background;
use App\Guesstimate\Document\CaseStudy;
use App\Guesstimate\Document\Media;
use App\Guesstimate\Support\Input;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Biblioteka teł i realizacje (ustawienia administratora). Pliki zawsze w bibliotece mediów WordPressa.
 */
class DocumentLibraryController extends Controller
{
	public function storeBackground(Request $request)
	{
		$back = route('gst.settings.index', ['section' => 'tla']);
		$name = mb_substr((string) Input::line($request->input('name')), 0, 150);
		$existing = (int) $request->input('attachment_id');

		if ($existing) {
			if (! Media::isImage($existing)) {
				return $this->redirectWith($back, 'Wybrany plik nie jest obrazem JPG, PNG ani WebP.', 'error');
			}

			$id = $existing;
		} else {
			[$id, $error] = Media::upload('file');

			if ($error) {
				return $this->redirectWith($back, $error, 'error');
			}
		}

		[$width, $height] = Media::size($id);
		$background = Background::create([
			'attachment_id' => $id,
			'name' => $name ?: (get_the_title($id) ?: 'Tło ' . $id),
			'fit' => array_key_exists((string) $request->input('fit'), Background::FITS) ? $request->input('fit') : 'cover',
			'width' => $width,
			'height' => $height,
			'sort' => (int) Background::max('sort') + 1,
		]);

		$message = sprintf('Dodano tło „%s”.', $background->name);

		if ($background->ratioWarning()) {
			$message .= sprintf(' Uwaga: proporcje %d×%d odbiegają od A4 poziomo (297 × 210) — przy dopasowaniu „cover” część obrazu zostanie przycięta.', $width, $height);
		}

		return $this->redirectWith($back, $message);
	}

	public function updateBackground(Request $request, Background $background)
	{
		$name = mb_substr((string) Input::line($request->input('name')), 0, 150);

		if ($name === '') {
			return $this->redirectWith(route('gst.settings.index', ['section' => 'tla']), 'Podaj nazwę tła.', 'error');
		}

		$background->update([
			'name' => $name,
			'fit' => array_key_exists((string) $request->input('fit'), Background::FITS) ? $request->input('fit') : $background->fit,
		]);

		return $this->redirectWith(route('gst.settings.index', ['section' => 'tla']), sprintf('Zapisano tło „%s”.', $name));
	}

	/**
	 * Usuwa tło z biblioteki Guesstimate. Plik w bibliotece mediów zostaje, chyba że administrator
	 * zaznaczy to wprost i potwierdzi (wpisując USUŃ). Opublikowane oferty mają własny snapshot adresu obrazu.
	 */
	public function destroyBackground(Request $request, Background $background)
	{
		$back = route('gst.settings.index', ['section' => 'tla']);
		$deleteFile = $request->boolean('delete_file');

		if ($deleteFile && mb_strtoupper(trim((string) $request->input('confirm'))) !== 'USUŃ') {
			return $this->redirectWith($back, 'Aby usunąć plik z biblioteki mediów, wpisz USUŃ w polu potwierdzenia.', 'error');
		}

		$attachment = $background->attachment_id;
		$name = $background->name;
		$background->delete();

		// Domyślne tła wskazujące usunięty wpis przestają obowiązywać.
		$settings = app(\App\Guesstimate\Settings\SettingsRepository::class);
		$document = (array) $settings->get('document');
		$document['backgrounds'] = array_filter((array) ($document['backgrounds'] ?? []), fn ($id) => (int) $id !== $background->id);
		$settings->set('document', $document);

		$stillUsed = Background::where('attachment_id', $attachment)->exists();

		if ($deleteFile && ! $stillUsed) {
			// Plik używany w opublikowanych ofertach chroni MediaRefs (snapshot wskazuje ten plik).
			if ($numbers = \App\Guesstimate\Document\MediaRefs::offersUsing($attachment)) {
				return $this->redirectWith($back, sprintf('Usunięto tło „%s” z biblioteki Guesstimate. Pliku nie usunięto — jest używany w opublikowanych ofertach: %s.', $name, implode(', ', array_slice($numbers, 0, 5))));
			}

			wp_delete_attachment($attachment, true);

			return $this->redirectWith($back, sprintf('Usunięto tło „%s” i plik z biblioteki mediów.', $name));
		}

		return $this->redirectWith($back, sprintf('Usunięto tło „%s” z biblioteki Guesstimate. Plik pozostał w bibliotece mediów WordPress.', $name));
	}

	public static function usage(Background $background): array
	{
		$pattern = '"background_id":' . $background->id . '[,}]';

		return [
			'published' => DB::table('gst_publications')->whereNull('withdrawn_at')->where('snapshot', 'regexp', $pattern)->distinct()->count('offer_id'),
			'drafts' => DB::table('gst_offer_versions')->whereNull('locked_at')->where('document', 'regexp', $pattern)->count(),
		];
	}

	public function storeCase(Request $request)
	{
		return $this->saveCase($request, new CaseStudy(['sort' => (int) CaseStudy::max('sort') + 1, 'is_active' => true]));
	}

	public function updateCase(Request $request, CaseStudy $caseStudy)
	{
		if ($request->boolean('delete')) {
			$name = $caseStudy->client_name;
			$caseStudy->delete();

			return $this->redirectWith(route('gst.settings.index', ['section' => 'realizacje']), sprintf('Usunięto realizację „%s” (zdjęcie zostało w bibliotece mediów, opublikowane oferty się nie zmieniają).', $name));
		}

		return $this->saveCase($request, $caseStudy);
	}

	private function saveCase(Request $request, CaseStudy $case)
	{
		$back = route('gst.settings.index', ['section' => 'realizacje']);
		$name = mb_substr((string) Input::line($request->input('client_name')), 0, 150);
		$url = Input::line($request->input('url'));
		$url = $url && ! preg_match('#^https?://#i', $url) ? 'https://' . $url : $url;

		if ($name === '') {
			return $this->redirectWith($back, 'Podaj nazwę klienta realizacji.', 'error');
		}

		if ($url && (! filter_var($url, FILTER_VALIDATE_URL) || mb_strlen($url) > 255)) {
			return $this->redirectWith($back, 'Podaj poprawny adres strony realizacji.', 'error');
		}

		$metrics = [];

		foreach (array_slice(array_values((array) $request->input('metrics', [])), 0, CaseStudy::MAX_METRICS) as $row) {
			$value = mb_substr((string) Input::line($row['value'] ?? ''), 0, 20);
			$label = mb_substr((string) Input::line($row['label'] ?? ''), 0, 80);

			if ($value !== '' || $label !== '') {
				$metrics[] = ['value' => $value, 'label' => $label];
			}
		}

		$attachment = $case->attachment_id;

		if (! empty($_FILES['image']['name'])) {
			[$id, $error] = Media::upload('image');

			if ($error) {
				return $this->redirectWith($back, $error, 'error');
			}

			$attachment = $id;
		} elseif ((int) $request->input('attachment_id') && Media::isImage((int) $request->input('attachment_id'))) {
			$attachment = (int) $request->input('attachment_id');
		} elseif ($request->boolean('remove_image')) {
			$attachment = null;
		}

		$case->fill([
			'client_name' => $name,
			'url' => $url,
			'description' => mb_substr((string) (Input::text($request->input('description')) ?? ''), 0, 400) ?: null,
			'metrics' => $metrics,
			'attachment_id' => $attachment,
			'is_active' => $case->exists ? $request->boolean('is_active') : true,
		])->save();

		return $this->redirectWith($back, sprintf('Zapisano realizację „%s”.', $name));
	}
}
