<?php

namespace App\Guesstimate\Http\Controllers;

use App\Guesstimate\Document\DocumentEditor;
use App\Guesstimate\Document\DocumentSettings;
use App\Guesstimate\Document\Media;
use App\Guesstimate\Offers\Offer;
use App\Guesstimate\Offers\OfferPolicy;
use App\Guesstimate\Offers\OfferService;
use App\Guesstimate\Offers\OfferVersion;
use App\Guesstimate\Offers\PublicationService;
use App\Guesstimate\Offers\PublicOffer;
use App\Guesstimate\Settings\SettingsRepository;
use App\Guesstimate\Support\Flash;
use App\Guesstimate\Document\DocumentView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Edytor wizualny dokumentu oferty: widok edytora, ramka podglądu, podgląd LIVE (bez zapisu),
 * świadomy zapis, pomiar dopasowania strony „Sytuacja” oraz wybór zdjęć z biblioteki mediów WordPressa.
 */
class DocumentController extends Controller
{
	public function __construct(
		private OfferPolicy $policy,
		private OfferService $offers,
		private SettingsRepository $settings,
		private PublicationService $publications,
	) {
	}

	/**
	 * „Edytuj dokument” — edytor bez przechodzenia przez kroki kreatora.
	 */
	public function edit(Offer $offer)
	{
		$this->policy->authorizeView($offer);

		return $this->editor($offer, 'document');
	}

	/**
	 * Widok edytora (także krok 6 kreatora — $mode = 'wizard').
	 */
	public function editor(Offer $offer, string $mode)
	{
		$version = $offer->currentVersion;
		abort_unless($version->isBudget(), 409, 'Dokument jest dostępny dla wersji w nowym modelu kalkulacji. Utwórz nową wersję oferty.');

		$data = DocumentEditor::data($offer, $version, $this->settings, $mode);
		$panel = $this->publications->panel($offer);
		$data['config']['outdated'] = $panel['publication']['outdated'];
		$data['config']['blockers'] = $panel['blockers'];

		// Komunikaty po przekierowaniu (np. po publikacji) pokazuje widok edytora — treść renderuje się przed układem.
		return view('guesstimate.editor.index', array_merge($data, $panel, ['canEdit' => $this->policy->canEdit($offer), 'messages' => Flash::pull()]));
	}

	/**
	 * Ramka podglądu (iframe): zapisany stan dokumentu w trybie edytora lub miniatur.
	 */
	public function frame(Request $request, Offer $offer, PublicOfferController $public): Response
	{
		$this->policy->authorizeView($offer);
		$version = $offer->currentVersion;
		abort_unless($version->isBudget() && $version->result, 409, 'Podgląd jest dostępny po poprawnej kalkulacji w nowym modelu.');
		$mode = $request->query('tryb') === 'miniatury' ? 'thumbs' : 'editor';

		return $public->render(PublicOffer::build($offer, $version, $this->settings->all(), true), ['preview' => true, 'mode' => $mode]);
	}

	/**
	 * Podgląd LIVE: strony dokumentu dla niezapisanego stanu edytora. Nic nie jest zapisywane.
	 */
	public function preview(Request $request, Offer $offer): JsonResponse
	{
		$this->policy->authorizeView($offer);
		$version = $offer->currentVersion;

		if (! $version->isBudget() || ! $version->result) {
			return response()->json(['error' => 'Podgląd jest dostępny po poprawnej kalkulacji w nowym modelu.'], 409);
		}

		$result = DocumentEditor::apply($version, $request->all(), $this->settings->all());
		$snapshot = $this->draftSnapshot($offer, $version, $result['draft_content'], $result['draft_document']);

		return response()->json([
			'html' => view('guesstimate.public.partials.pages', ['offer' => DocumentView::fromSnapshot($snapshot), 'mode' => 'editor'])->render(),
			'errors' => (object) $result['errors'],
		]);
	}

	/**
	 * Świadomy zapis dokumentu w bieżącej (roboczej) wersji. Publikacja klienta się nie zmienia.
	 */
	public function save(Request $request, Offer $offer): JsonResponse
	{
		$this->policy->authorizeEdit($offer);
		$version = $offer->currentVersion;

		if ($error = $this->locked($offer, $version)) {
			return $error;
		}

		$result = DocumentEditor::apply($version, $request->all(), $this->settings->all());

		if ($result['errors']) {
			return response()->json(['error' => 'Nie zapisano zmian — popraw zaznaczone pola.', 'errors' => $result['errors']], 422);
		}

		$document = $result['document'];
		$document['fit_report'] = $this->report((array) $request->input('fit', []), $document, $result['content']);

		$version->update(['content' => $result['content'], 'document' => $document]);
		$this->offers->touch($offer, $version->fresh(), 'document', recalculate: false);

		return response()->json($this->status($offer->fresh()));
	}

	/**
	 * Pomiar dopasowania zapisanego stanu (edytor bez niezapisanych zmian). Zapisuje tylko metadane pomiaru.
	 */
	public function fit(Request $request, Offer $offer): JsonResponse
	{
		$this->policy->authorizeEdit($offer);
		$version = $offer->currentVersion;

		if ($error = $this->locked($offer, $version)) {
			return $error;
		}

		$doc = DocumentSettings::forVersion($version, $this->settings->all());
		$doc['fit_report'] = $this->report((array) $request->input('fit', []), $doc, (array) $version->content);
		$version->update(['document' => $doc]);

		return response()->json($this->status($offer->fresh()));
	}

	/**
	 * Obrazy z biblioteki mediów WordPressa (wyszukiwanie, stronicowanie).
	 */
	public function media(Request $request): JsonResponse
	{
		abort_unless(current_user_can(\App\Guesstimate\Auth\Capabilities::CREATE_OFFERS), 403);

		return response()->json(Media::browse((string) $request->query('q', ''), (int) $request->query('page', 1)));
	}

	/**
	 * Przesłanie zdjęcia do biblioteki mediów WordPressa (bez własnego magazynu plików).
	 */
	public function upload(Request $request): JsonResponse
	{
		abort_unless(current_user_can(\App\Guesstimate\Auth\Capabilities::CREATE_OFFERS) && current_user_can('upload_files'), 403, 'Nie masz uprawnień do przesyłania plików.');
		[$id, $error] = Media::upload('file');

		if ($error) {
			return response()->json(['error' => $error], 422);
		}

		return response()->json(DocumentEditor::imageCard($id));
	}

	private function locked(Offer $offer, OfferVersion $version): ?JsonResponse
	{
		$message = match (DocumentEditor::lockReason($offer, $version)) {
			null => null,
			'locked' => 'Ta wersja jest zamrożona (oferta wysłana). Utwórz wersję roboczą, aby zmienić dokument.',
			'cancelled' => 'Anulowanej oferty nie można edytować.',
			'legacy' => 'Wersja w poprzednim modelu kalkulacji — utwórz nową wersję.',
			default => 'Nie możesz edytować tej oferty.',
		};

		return $message ? response()->json(['error' => $message], 409) : null;
	}

	/**
	 * Wynik pomiaru z przeglądarki dla strony „Sytuacja”, powiązany ze skrótem mierzonego stanu.
	 */
	private function report(array $fit, array $document, array $content): array
	{
		if (! isset($fit['situation']) || ! is_array($fit['situation'])) {
			return (array) ($document['fit_report'] ?? []);
		}

		return ['situation' => [
			'overflow' => ! empty($fit['situation']['overflow']),
			'level' => max(0, min(9, (int) ($fit['situation']['level'] ?? 0))),
			'hash' => DocumentSettings::fitHash($document, \App\Guesstimate\Offers\OfferContent::normalize($content)),
			'measured_at' => now()->toIso8601String(),
		]];
	}

	private function status(Offer $offer): array
	{
		$version = $offer->currentVersion;
		$panel = $this->publications->panel($offer);

		return [
			'state' => DocumentEditor::state($version, $this->settings->all()),
			'fitReport' => DocumentSettings::forVersion($version, $this->settings->all())['fit_report']['situation'] ?? null,
			'outdated' => $panel['publication']['outdated'],
			'blockers' => $panel['blockers'],
			'message' => 'Zapisano zmiany w wersji roboczej.',
		];
	}

	/**
	 * Snapshot dla niezapisanego stanu: atrybuty wersji podmienione tylko w pamięci (bez zapisu).
	 */
	private function draftSnapshot(Offer $offer, OfferVersion $version, array $content, array $document): array
	{
		$draft = clone $version;
		$draft->setRawAttributes($version->getAttributes());
		$draft->setRelations($version->getRelations());
		$draft->content = $content;
		$draft->document = $document;

		return PublicOffer::build($offer, $draft, $this->settings->all(), true);
	}
}
