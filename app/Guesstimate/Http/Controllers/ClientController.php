<?php

namespace App\Guesstimate\Http\Controllers;

use App\Guesstimate\Auth\Capabilities;
use App\Guesstimate\Clients\Client;
use App\Guesstimate\Clients\ClientService;
use App\Guesstimate\Offers\OfferQuery;
use App\Guesstimate\Offers\OfferStatus;
use App\Guesstimate\Support\Flash;
use App\Guesstimate\Support\Format;
use Illuminate\Http\Request;

class ClientController extends Controller
{
	public function __construct(private ClientService $clients, private OfferQuery $offers)
	{
	}

	public function index(Request $request)
	{
		return view('guesstimate.clients.index', $this->indexData($request));
	}

	public function store(Request $request)
	{
		['data' => $data, 'errors' => $errors] = $this->clients->validate($request->all());

		if ($errors) {
			return $this->invalid('guesstimate.clients.index', array_merge($this->indexData($request), ['openModal' => 'client-create']), $errors);
		}

		$client = $this->clients->create($data);

		return $this->redirectWith(route('gst.clients.show', $client), sprintf('Dodano klienta „%s”.', $client->company_name));
	}

	public function show(Client $client)
	{
		return view('guesstimate.clients.show', $this->showData($client));
	}

	public function update(Request $request, Client $client)
	{
		['data' => $data, 'errors' => $errors] = $this->clients->validate($request->all());

		if ($errors) {
			return $this->invalid('guesstimate.clients.show', array_merge($this->showData($client), ['openModal' => 'client-edit']), $errors);
		}

		$client->update($data);

		return $this->redirectWith(route('gst.clients.show', $client), 'Zapisano zmiany klienta.');
	}

	/**
	 * Usunięcie klienta (soft delete). Uprawnienie MANAGE_CLIENTS (Administrator, Manager) sprawdza trasa i kontroler;
	 * zależności (oferty, także w koszu) sprawdza serwis w transakcji z blokadą wiersza. Tylko POST + nonce.
	 */
	public function destroy(int $client)
	{
		abort_unless(current_user_can(Capabilities::MANAGE_CLIENTS), 403, 'Nie możesz usuwać klientów.');
		$result = $this->clients->delete($client, get_current_user_id());
		$back = fn () => request()->input('from') === 'show' && $result['client'] ? route('gst.clients.show', $result['client']) : route('gst.clients.index');

		return match ($result['result']) {
			'deleted' => $this->deleted($client),
			'in_use' => $this->redirectWith($back(), sprintf('Nie można usunąć klienta, ponieważ jest powiązany z istniejącymi ofertami. Powiązane oferty: %d%s.', $result['offers'], $result['trashed_offers'] ? sprintf(' (w tym w koszu: %d)', $result['trashed_offers']) : ''), 'error'),
			'already' => $this->redirectWith(route('gst.clients.index', ['widok' => 'usunieci']), 'Ten klient został już usunięty.', 'error'),
			default => $this->redirectWith(route('gst.clients.index'), 'Nie znaleziono klienta.', 'error'),
		};
	}

	private function deleted(int $client)
	{
		Flash::success('Klient został usunięty.', ['url' => route('gst.clients.restore', $client), 'label' => 'Cofnij']);

		return redirect()->to(route('gst.clients.index'));
	}

	public function restore(int $client)
	{
		abort_unless(current_user_can(Capabilities::MANAGE_CLIENTS), 403, 'Nie możesz przywracać klientów.');
		$result = $this->clients->restore($client);

		return match ($result['result']) {
			'restored' => $this->redirectWith(route('gst.clients.show', $result['client']), sprintf('Przywrócono klienta „%s”.', $result['client']->company_name)),
			'active' => $this->redirectWith(route('gst.clients.show', $result['client']), 'Ten klient jest aktywny.'),
			default => $this->redirectWith(route('gst.clients.index', ['widok' => 'usunieci']), 'Nie znaleziono klienta.', 'error'),
		};
	}

	private function indexData(Request $request): array
	{
		$term = mb_substr(trim((string) $request->query('q', '')), 0, 100);
		$deletedView = $request->query('widok') === 'usunieci';

		return [
			'term' => $term,
			'view' => $deletedView ? 'deleted' : 'active',
			'clients' => $deletedView ? collect() : $this->clients->search($term)->limit(500)->get(),
			'deleted' => $deletedView ? $this->clients->deleted($term) : [],
			'deletedCount' => Client::onlyTrashed()->count(),
		];
	}

	private function showData(Client $client): array
	{
		$offers = array_map(fn ($row) => [
			'id' => $row->id,
			'number' => $row->number,
			'project' => $row->project_name,
			'status' => OfferStatus::from($row->status),
			'range' => Format::moneyRange($row->low_min, $row->high_max, $row->currency),
			'version' => $row->version_no,
			'author' => $row->author_name,
			'created' => Format::date($row->created_at),
		], $this->offers->forClient($client->id));

		return [
			'client' => $client,
			'offers' => $offers,
			'offersAll' => $client->offers()->withTrashed()->count(),
			'offersTrashed' => $client->offers()->onlyTrashed()->count(),
		];
	}
}
