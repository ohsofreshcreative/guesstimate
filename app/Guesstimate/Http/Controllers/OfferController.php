<?php

namespace App\Guesstimate\Http\Controllers;

use App\Guesstimate\Clients\Client;
use App\Guesstimate\Offers\Offer;
use App\Guesstimate\Offers\OfferPolicy;
use App\Guesstimate\Offers\OfferQuery;
use App\Guesstimate\Offers\OfferService;
use App\Guesstimate\Offers\OfferDisplayStatus;
use App\Guesstimate\Offers\OfferStatus;
use App\Guesstimate\Offers\OfferSummary;
use App\Guesstimate\Offers\OfferVersion;
use App\Guesstimate\Offers\PublicationService;
use App\Guesstimate\Offers\PublicationStatus;
use App\Guesstimate\Support\Format;
use App\Guesstimate\Support\Input;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Archiwum ofert: lista, podgląd, statusy, wersje i duplikowanie.
 * Tworzenie i edycję treści oferty obsługuje WizardController.
 */
class OfferController extends Controller
{
	public function __construct(
		private OfferQuery $query,
		private OfferPolicy $policy,
		private OfferService $offers,
		private PublicationService $publications,
	) {
	}

	public function index(Request $request)
	{
		$result = $this->query->search($request->query());

		return view('guesstimate.offers.index', [
			'result' => $result,
			'rows' => OfferSummary::rows($result['rows']),
			'filters' => $result['filters'],
			'statuses' => OfferDisplayStatus::options(),
			'types' => OfferVersion::PROJECT_TYPES,
			'authors' => $this->policy->viewsAll() ? $this->query->authors() : [],
			'clientName' => $result['filters']['client'] ? Client::withTrashed()->find($result['filters']['client'])?->company_name : null,
			'canCreate' => $this->policy->canCreate(),
			'onlineFilters' => OfferQuery::PUBLICATION_FILTERS,
		]);
	}

	public function show(Request $request, Offer $offer)
	{
		$this->policy->authorizeView($offer);

		$offer->load(['client', 'versions', 'currentVersion']);
		$versionNo = (int) $request->query('wersja', 0);
		$version = $versionNo ? $offer->versions->firstWhere('version_no', $versionNo) : $offer->currentVersion;
		abort_if($version === null, 404);

		return view('guesstimate.offers.show', [
			'offer' => $offer,
			'version' => $version,
			'isCurrent' => $version->id === $offer->current_version_id,
			'summary' => OfferSummary::version($version),
			'publication' => $offer->currentVersion->isBudget() ? $this->publications->panel($offer) : null,
			'versions' => OfferSummary::versions($offer),
			'events' => OfferSummary::events($offer),
			'canEdit' => $this->policy->canEdit($offer),
			'canDuplicate' => $this->policy->canDuplicate($offer),
			'transitions' => $offer->status->transitions(),
			'meta' => [
				'author' => self::userName($offer->author_id),
				'editor' => self::userName($offer->last_editor_id),
				'created' => Format::date($offer->created_at, true),
				'updated' => Format::date($offer->updated_at, true),
			],
		]);
	}

	public function status(Request $request, Offer $offer)
	{
		$this->policy->authorizeEdit($offer);
		$to = OfferStatus::tryFrom((string) $request->input('status'));

		if ($to === null) {
			return $this->redirectWith(route('gst.offers.show', $offer), 'Wybierz status z listy.', 'error');
		}

		try {
			$this->offers->changeStatus($offer, $to, Input::line($request->input('note')));
		} catch (InvalidArgumentException $e) {
			return $this->redirectWith(route('gst.offers.show', $offer), $e->getMessage(), 'error');
		}

		$message = sprintf('Status oferty %s: %s.', $offer->number, $to->label());

		if ($to->locksVersion() && $offer->currentVersion->version_no) {
			$message .= ' Wersja ' . $offer->currentVersion->version_no . ' jest teraz zamrożona — kolejne zmiany utworzą nową wersję.';
		}

		$target = $request->input('back') === 'document' ? route('gst.offers.document', $offer) : route('gst.offers.show', $offer);

		return $this->redirectWith($target, $message);
	}

	public function duplicate(Offer $offer)
	{
		abort_unless($this->policy->canDuplicate($offer), 403, 'Nie możesz duplikować tej oferty.');
		$copy = $this->offers->duplicate($offer);

		return $this->redirectWith(route('gst.offers.wizard', ['offer' => $copy, 'step' => 1]), sprintf('Utworzono ofertę %s jako kopię %s.', $copy->number, $offer->number));
	}

	public function newVersion(Request $request, Offer $offer)
	{
		$this->policy->authorizeEdit($offer);

		if (! $offer->status->isEditable()) {
			return $this->redirectWith(route('gst.offers.show', $offer), 'Anulowanej oferty nie można edytować. Najpierw przywróć ją do roboczej.', 'error');
		}

		$version = $this->offers->createVersion($offer, Input::line($request->input('note')));

		// Z edytora dokumentu: wersja robocza do zmiany dokumentu — wracamy do edytora (bez kroków kreatora).
		$target = $request->input('back') === 'document'
			? route('gst.offers.document', $offer)
			: route('gst.offers.wizard', ['offer' => $offer, 'step' => 1]);

		return $this->redirectWith($target, sprintf('Utworzono wersję %d oferty %s. Wcześniejsze wersje pozostają bez zmian.', $version->version_no, $offer->number));
	}

	public static function userName(?int $id): string
	{
		if (! $id) {
			return '—';
		}

		$user = get_userdata($id);

		return $user ? ($user->display_name ?: $user->user_login) : 'Usunięty użytkownik';
	}
}
