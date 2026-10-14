<?php

namespace App\Guesstimate\Http\Controllers;

use App\Guesstimate\Offers\Offer;
use App\Guesstimate\Offers\OfferPolicy;
use App\Guesstimate\Offers\TrashService;
use App\Guesstimate\Support\Flash;
use App\Guesstimate\Support\Format;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Kosz ofert. Uprawnienia sprawdzane po stronie serwera (OfferPolicy), wszystkie zmiany przez POST z nonce.
 */
class TrashController extends Controller
{
	public function __construct(private OfferPolicy $policy, private TrashService $trash)
	{
	}

	public function index()
	{
		abort_unless($this->policy->canManageTrash(), 403, 'Kosz ofert jest dostępny dla managera i administratora.');

		$offers = Offer::onlyTrashed()->with(['client', 'currentVersion'])->orderByDesc('deleted_at')->limit(500)->get();

		return view('guesstimate.offers.trash', [
			'rows' => $offers->map(fn (Offer $o) => [
				'id' => $o->id,
				'number' => $o->number,
				'project' => $o->currentVersion?->project_name ?? '—',
				'client' => $o->client?->company_name ?? '—',
				'author' => OfferController::userName($o->author_id),
				'deleted_at' => Format::date($o->deleted_at, true),
				'deleted_by' => OfferController::userName($o->deleted_by),
			])->all(),
			'canForceDelete' => $this->policy->canForceDelete(),
		]);
	}

	public function trash(Request $request, Offer $offer)
	{
		abort_unless($this->policy->canTrash($offer), 403, 'Nie możesz przenieść tej oferty do kosza.');
		$this->trash->trash($offer);

		Flash::success('Oferta została przeniesiona do kosza.', ['url' => route('gst.offers.trash.restore', ['offer' => $offer->id, 'wroc' => 'archiwum']), 'label' => 'Cofnij']);

		return redirect()->to(route('gst.offers.index'));
	}

	public function restore(Request $request, int $offer)
	{
		$model = Offer::onlyTrashed()->findOrFail($offer);
		abort_unless($this->policy->canRestore($model), 403, 'Nie możesz przywrócić tej oferty.');

		try {
			$this->trash->restore($model);
		} catch (InvalidArgumentException $e) {
			return $this->redirectWith(route('gst.offers.index'), $e->getMessage(), 'error');
		}

		$message = sprintf('Przywrócono ofertę %s.', $model->number) . ($model->public_token ? ' Publiczny link pozostaje wyłączony — opublikuj ofertę ponownie, gdy będzie gotowa.' : '');

		// „Cofnij” z komunikatu w archiwum wraca do archiwum; „Przywróć” w koszu — do szczegółów oferty.
		$target = $request->query('wroc') === 'archiwum' ? route('gst.offers.index') : route('gst.offers.show', $model);

		return $this->redirectWith($target, $message);
	}

	public function destroy(Request $request, int $offer)
	{
		abort_unless($this->policy->canForceDelete(), 403, 'Trwałe usuwanie ofert jest dostępne tylko dla administratora.');
		$model = Offer::onlyTrashed()->findOrFail($offer);

		if (mb_strtoupper(trim((string) $request->input('confirm'))) !== 'USUŃ') {
			return $this->redirectWith(route('gst.offers.trash'), 'Aby usunąć ofertę na stałe, wpisz USUŃ w polu potwierdzenia.', 'error');
		}

		try {
			$this->trash->forceDelete($model);
		} catch (InvalidArgumentException $e) {
			return $this->redirectWith(route('gst.offers.trash'), $e->getMessage(), 'error');
		}

		return $this->redirectWith(route('gst.offers.trash'), sprintf('Oferta %s została usunięta na stałe.', $model->number));
	}
}
