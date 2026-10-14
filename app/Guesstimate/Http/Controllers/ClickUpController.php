<?php

namespace App\Guesstimate\Http\Controllers;

use App\Guesstimate\Integrations\ClickUp\ClickUpAccount;
use App\Guesstimate\Integrations\ClickUp\ClickUpConnection;
use App\Guesstimate\Integrations\ClickUp\ClickUpMapper;
use App\Guesstimate\Integrations\ClickUp\ClickUpSync;
use App\Guesstimate\Integrations\ClickUp\OAuthApp;
use App\Guesstimate\Integrations\IntegrationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * „Moje integracje” → ClickUp. Każda akcja dotyczy wyłącznie połączenia ZALOGOWANEGO użytkownika
 * (get_current_user_id()); identyfikator użytkownika nigdy nie jest brany z żądania. Odpowiedzi nie zawierają
 * tokenów. Akcje zmieniające stan: POST + nonce; callback OAuth: GET z jednorazowym `state`.
 */
class ClickUpController extends Controller
{
	private const CALLBACK_MESSAGES = [
		'connected' => ['success', 'Połączono z ClickUp. Wybierz Workspace i listy z klientami.'],
		'denied' => ['error', 'Anulowano autoryzację w ClickUp. Konto nie zostało połączone.'],
		'missing_code' => ['error', 'ClickUp nie przekazał kodu autoryzacji. Spróbuj połączyć ponownie.'],
		'invalid_code' => ['error', 'Nieprawidłowy kod autoryzacji z ClickUp. Spróbuj połączyć ponownie.'],
		'invalid_state' => ['error', 'Link autoryzacji wygasł albo jest nieprawidłowy. Kliknij „Połącz z ClickUp” jeszcze raz.'],
		'wrong_user' => ['error', 'Autoryzację rozpoczął inny użytkownik. Zaloguj się na swoje konto i połącz ClickUp ponownie.'],
		'not_configured' => ['error', 'Integracja ClickUp nie jest skonfigurowana przez administratora.'],
		'exchange_failed' => ['error', 'ClickUp odrzucił autoryzację (wymiana kodu na token nie powiodła się). Spróbuj ponownie.'],
	];

	public function __construct(private ClickUpAccount $account, private ClickUpSync $sync)
	{
	}

	public function index()
	{
		$connection = $this->connection();
		$config = $connection?->settings();

		return view('guesstimate.integrations.index', [
			'app' => OAuthApp::configuration(),
			'connection' => $connection,
			'missing' => $connection?->missing() ?? [],
			'editor' => [
				'connected' => (bool) $connection?->isConnected(),
				'config' => $config,
				'teams' => array_values((array) ($connection?->authorized_teams ?? [])),
				'targets' => ClickUpMapper::TARGETS,
				'sync' => $connection ? $this->sync->status($connection, false) : null,
				'urls' => [
					'options' => route('gst.integrations.clickup.options'),
					'save' => route('gst.integrations.clickup.save'),
					'test' => route('gst.integrations.clickup.test'),
					'sync' => route('gst.integrations.clickup.sync'),
					'status' => route('gst.integrations.clickup.status'),
				],
			],
		]);
	}

	public function connect()
	{
		try {
			return redirect()->away($this->account->start(get_current_user_id()));
		} catch (IntegrationException $e) {
			return $this->redirectWith(route('gst.integrations.index'), $e->getMessage(), 'error');
		}
	}

	public function callback(Request $request)
	{
		$result = $this->account->callback($request->query(), get_current_user_id());
		[$type, $message] = self::CALLBACK_MESSAGES[$result] ?? ['error', 'Nie udało się połączyć z ClickUp.'];

		// Konfiguracja przeniesiona z poprzedniej wersji jest kompletna → pierwsza synchronizacja bez klikania.
		if ($result === 'connected' && ($connection = $this->connection()) && $connection->missing() === ['Zsynchronizuj firmy.']) {
			try {
				$this->sync->start($connection, 'full', 'config');
				$message .= ' Synchronizacja firm rozpoczęła się automatycznie.';
			} catch (IntegrationException) {
			}
		}

		return $this->redirectWith(route('gst.integrations.index'), $message, $type);
	}

	public function test(): JsonResponse
	{
		return response()->json($this->account->test($this->required()));
	}

	public function options(Request $request): JsonResponse
	{
		$level = (string) $request->input('level');
		$id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $request->input('id'));

		try {
			return response()->json(['items' => $this->account->options($this->required(), $level, $id)]);
		} catch (IntegrationException $e) {
			return response()->json(['error' => $e->getMessage(), 'reconnect' => $e->getCode() === 401], 422);
		}
	}

	public function save(Request $request): JsonResponse
	{
		$connection = $this->required();
		$errors = $this->account->saveConfig($connection, $request->all());

		if ($errors) {
			return response()->json(['error' => 'Nie zapisano konfiguracji — popraw zaznaczone pola.', 'errors' => $errors], 422);
		}

		$connection->refresh();
		$message = 'Zapisano konfigurację ClickUp.';

		// Pierwsza / nowa pełna synchronizacja startuje automatycznie, gdy zmienił się zakres (Workspace, listy, mapowanie).
		if ($connection->list_state === null || ($connection->list_state['lists_key'] ?? '') !== $this->sync->listsKey($connection) || $connection->full_synced_at === null) {
			try {
				$this->sync->restart($connection);
				$message .= ' Rozpoczęto synchronizację firm.';
			} catch (IntegrationException $e) {
				$message .= ' ' . $e->getMessage();
			}

			$connection->refresh();
		}

		return response()->json(['config' => $connection->settings(), 'missing' => $connection->missing(), 'sync' => $this->sync->status($connection, false), 'message' => $message]);
	}

	public function startSync(): JsonResponse
	{
		try {
			return response()->json($this->sync->start($this->required()));
		} catch (IntegrationException $e) {
			return response()->json(['error' => $e->getMessage()], 422);
		}
	}

	public function syncStatus(): JsonResponse
	{
		$connection = $this->required();
		$status = $this->sync->status($connection);

		return response()->json($status + ['missing' => $connection->fresh()->missing()]);
	}

	public function disconnect()
	{
		$this->account->disconnect(get_current_user_id());

		return $this->redirectWith(route('gst.integrations.index'), 'Rozłączono ClickUp. Twój indeks firm został usunięty; klienci zaimportowani do Guesstimate zostają. Dostęp aplikacji możesz też odebrać w ClickUp (Settings → Apps).');
	}

	private function connection(): ?ClickUpConnection
	{
		return ClickUpConnection::forUser(get_current_user_id());
	}

	private function required(): ClickUpConnection
	{
		$connection = $this->connection();
		abort_unless($connection !== null, 409, 'Połącz najpierw swoje konto ClickUp.');

		return $connection;
	}
}
