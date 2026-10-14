<?php

namespace App\Guesstimate\Http\Controllers;

use App\Guesstimate\Auth\Capabilities;
use App\Guesstimate\Clients\ClientPresenter;
use App\Guesstimate\Clients\ClientService;
use App\Guesstimate\Integrations\ClickUp\ClickUpConnection;
use App\Guesstimate\Integrations\ClickUp\ClickUpDirectory;
use App\Guesstimate\Integrations\IntegrationException;
use App\Guesstimate\Support\Input;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Wyszukiwarka klientów w kreatorze: wspólna baza klientów Guesstimate + prywatny indeks ClickUp
 * zalogowanego użytkownika (bez zapytań do ClickUp przy wpisywaniu). Import trafia do wspólnej bazy.
 */
class ClientLookupController extends Controller
{
	public function __construct(private ClientService $clients, private ClickUpDirectory $directory)
	{
	}

	public function search(Request $request): JsonResponse
	{
		abort_unless(current_user_can(Capabilities::CREATE_OFFERS), 403);
		$term = mb_substr((string) Input::line($request->query('q')), 0, 100);
		$userId = get_current_user_id();

		$local = $this->clients->search($term)->limit(8)->get()->map(fn ($c) => ClientPresenter::card($c))->all();
		$connection = ClickUpConnection::forUser($userId);
		$status = $this->directory->state($connection);
		$remote = [];

		if ($status === 'ok') {
			if (mb_strlen(ClickUpDirectory::fold($term)) < 2) {
				$status = 'short';
			} else {
				$localIds = array_filter(array_column($local, 'id'));
				// Firma już zaimportowana i widoczna w wynikach lokalnych nie jest powtarzana w sekcji ClickUp.
				$remote = array_values(array_filter($this->directory->search($userId, $term), fn ($r) => $r['local_id'] === null || ! in_array($r['local_id'], $localIds, true)));
			}
		}

		return response()->json([
			'local' => $local,
			'clickup' => $remote,
			// not_connected | reconnect | not_configured | not_synced | short | ok
			'clickup_status' => $status,
			'clickup_syncing' => $connection && in_array($connection->sync_status, ['queued', 'running', 'waiting'], true),
			'integrations_url' => route('gst.integrations.index'),
			'can_import' => current_user_can(Capabilities::CREATE_CLIENTS),
		]);
	}

	public function import(Request $request): JsonResponse
	{
		abort_unless(current_user_can(Capabilities::CREATE_CLIENTS), 403, 'Nie możesz dodawać klientów.');
		$choice = (int) $request->input('client_id') ?: null;

		try {
			$result = $this->directory->import(get_current_user_id(), (string) $request->input('clickup_id'), $choice, $request->boolean('create_new'), $request->boolean('restore'));
		} catch (IntegrationException $e) {
			return response()->json(['error' => $e->getMessage()], 422);
		}

		if ($result['client'] === null && ! empty($result['deleted'])) {
			// Firma była klientem Guesstimate, ale została usunięta — przywrócenie albo świadomy import jako nowy.
			$deleted = $result['deleted'];

			return response()->json([
				'needs_choice' => true,
				'deleted' => ClientPresenter::card($deleted) + [
					'deleted_at' => $deleted->deleted_at ? wp_date('d.m.Y H:i', $deleted->deleted_at->getTimestamp()) : null,
					'deleted_by' => $deleted->deleted_by ? (get_userdata((int) $deleted->deleted_by)->display_name ?? null) : null,
				],
				'can_restore' => current_user_can(Capabilities::MANAGE_CLIENTS),
				'candidates' => [],
				'skipped' => $result['skipped'],
			], 409);
		}

		if ($result['client'] === null) {
			// Niepewne dopasowanie — wybór użytkownika: istniejący klient albo nowy rekord.
			return response()->json([
				'needs_choice' => true,
				'candidates' => array_map(fn ($c) => ClientPresenter::card($c), $result['candidates']),
				'skipped' => $result['skipped'],
			], 409);
		}

		return response()->json([
			'client' => ClientPresenter::card($result['client']),
			'created' => $result['created'],
			'linked' => $result['linked'],
			'skipped' => $result['skipped'],
		]);
	}
}
