<?php

namespace App\Guesstimate\Integrations\ClickUp;

use App\Guesstimate\Integrations\IntegrationException;
use App\Guesstimate\Settings\SettingsRepository;
use App\Guesstimate\Support\Secrets;
use Illuminate\Support\Facades\DB;

/**
 * Połączenie ClickUp bieżącego użytkownika: OAuth (start, callback), token, konfiguracja list i mapowania,
 * sprawdzenie połączenia, rozłączenie. Każda metoda działa wyłącznie na połączeniu użytkownika, którego
 * identyfikator przekazuje kontroler (zawsze get_current_user_id()) — nie ma odczytu cudzych tokenów.
 *
 * Bezpieczeństwo OAuth: start tylko przez POST z nonce (CSRF), `state` = 32 losowe bajty, po stronie serwera
 * przechowywany tylko skrót (10 min, jednorazowy, przypisany do użytkownika); callback wymaga zalogowanego
 * użytkownika, który rozpoczął autoryzację. Token szyfrowany (Secrets::seal z kontekstem użytkownika).
 */
class ClickUpAccount
{
	private const STATE_TTL = 600;
	private const STATE_PREFIX = 'gst_cu_state_';

	public const RECONNECT_MESSAGE = 'Połączenie z ClickUp wygasło lub zostało cofnięte. Połącz ponownie.';
	public const TEAM_MESSAGE = 'Brak dostępu do tego Workspace w ClickUp — autoryzuj go („Zmień autoryzację”) albo wybierz inny.';

	public function __construct(private Secrets $secrets, private SettingsRepository $settings)
	{
	}

	/**
	 * Adres autoryzacji ClickUp dla użytkownika (przekierowanie przeglądarki).
	 */
	public function start(int $userId): string
	{
		$config = OAuthApp::configuration();

		if (! $config['configured']) {
			throw new IntegrationException('Integracja ClickUp nie jest skonfigurowana przez administratora (brak: ' . implode(', ', $config['missing']) . ').');
		}

		$state = bin2hex(random_bytes(32));
		set_transient(self::STATE_PREFIX . hash('sha256', $state), ['user_id' => $userId, 'created' => time()], self::STATE_TTL);

		return OAuthApp::authorizeUrl($state);
	}

	/**
	 * Powrót z ClickUp (?code=&state= albo ?error=). Zwraca kod wyniku do komunikatu (bez danych wrażliwych).
	 */
	public function callback(array $query, int $userId): string
	{
		$state = is_string($query['state'] ?? null) ? $query['state'] : '';
		$code = is_string($query['code'] ?? null) ? $query['code'] : '';

		if (! preg_match('/^[a-f0-9]{64}$/', $state)) {
			return 'invalid_state';
		}

		$key = self::STATE_PREFIX . hash('sha256', $state);
		$saved = get_transient($key);
		delete_transient($key); // jednorazowy — także przy błędzie

		if (! is_array($saved) || time() - (int) ($saved['created'] ?? 0) > self::STATE_TTL) {
			return 'invalid_state';
		}

		if ($userId <= 0 || (int) $saved['user_id'] !== $userId) {
			return 'wrong_user';
		}

		if ($code === '') {
			return ! empty($query['error']) ? 'denied' : 'missing_code';
		}

		if (strlen($code) > 512 || ! preg_match('/^[A-Za-z0-9._~-]+$/', $code)) {
			return 'invalid_code';
		}

		if (! OAuthApp::configuration()['configured']) {
			return 'not_configured';
		}

		try {
			$token = ClickUpClient::exchangeCode((string) OAuthApp::clientId(), (string) OAuthApp::clientSecret(), $code);
			$api = new ClickUpClient($token);
			$user = $api->user();
			$teams = $this->teamsList($api->teams());
		} catch (IntegrationException) {
			return 'exchange_failed';
		}

		DB::transaction(function () use ($userId, $token, $user, $teams) {
			$connection = ClickUpConnection::forUser($userId) ?? new ClickUpConnection(['user_id' => $userId]);
			$previousUser = $connection->clickup_user_id;
			$newUser = isset($user['id']) ? (string) $user['id'] : null;

			// Inne konto ClickUp niż poprzednio → prywatny indeks poprzedniego konta jest usuwany.
			if ($connection->exists && $previousUser !== null && $previousUser !== $newUser) {
				DB::table('gst_clickup_index')->where('user_id', $userId)->delete();
				$connection->fill(['config' => null, 'synced_at' => null, 'synced_count' => 0, 'sync_status' => 'never', 'sync_state' => null, 'list_state' => null, 'full_synced_at' => null, 'next_sync_at' => null]);
			}

			$connection->fill([
				'token_ciphertext' => $this->secrets->seal($token, self::context($userId)),
				'status' => ClickUpConnection::CONNECTED,
				'clickup_user_id' => $newUser,
				'clickup_username' => mb_substr((string) ($user['username'] ?? $user['email'] ?? ''), 0, 190) ?: null,
				'authorized_teams' => $teams,
				'connected_at' => now(),
				'last_checked_at' => now(),
				'last_error' => null,
			]);

			if (! $connection->config) {
				$connection->config = $this->legacyConfig($teams);
			}

			$connection->save();
		});

		return 'connected';
	}

	/**
	 * Klient API dla połączenia użytkownika (token odszyfrowany tylko na czas żądania).
	 */
	public function api(ClickUpConnection $connection): ClickUpClient
	{
		$token = $connection->isConnected() ? $this->secrets->open($connection->token_ciphertext, self::context($connection->user_id)) : null;

		if ($token === null) {
			if ($connection->isConnected()) {
				$this->markReconnect($connection, 'Nie można odczytać tokenu (zmiana kluczy WordPress).');
			}

			throw new IntegrationException(self::RECONNECT_MESSAGE, 401);
		}

		return new ClickUpClient($token);
	}

	/**
	 * Wywołanie API z obsługą cofniętego dostępu (401/403 → połączenie wymaga ponownej autoryzacji).
	 */
	public function call(ClickUpConnection $connection, callable $fn): mixed
	{
		try {
			return $fn($this->api($connection));
		} catch (IntegrationException $e) {
			if (in_array($e->getCode(), [401, 403], true) && self::teamNotAuthorized($e->apiCode)) {
				// Token działa, ale ten Workspace nie został autoryzowany dla aplikacji — nie zrywamy połączenia.
				throw new IntegrationException(self::TEAM_MESSAGE, 403);
			}

			if ($e->getCode() === 401) {
				$this->markReconnect($connection, $e->getMessage() . ($e->apiCode ? ' (' . $e->apiCode . ')' : ''));

				throw new IntegrationException(self::RECONNECT_MESSAGE, 401);
			}

			throw $e;
		}
	}

	/**
	 * Dokumentacja ClickUp (Common errors): OAUTH_023, OAUTH_026, OAUTH_027, OAUTH_029–OAUTH_045 =
	 * „Team not authorized” (Workspace nieautoryzowany dla aplikacji). OAUTH_019/021/025/077 = token cofnięty.
	 */
	public static function teamNotAuthorized(?string $code): bool
	{
		if ($code === null || ! preg_match('/^OAUTH_(\d+)$/', $code, $m)) {
			return false;
		}

		$n = (int) $m[1];

		return in_array($n, [23, 26, 27], true) || ($n >= 29 && $n <= 45);
	}

	public function markReconnect(ClickUpConnection $connection, string $reason): void
	{
		$connection->update(['status' => ClickUpConnection::RECONNECT, 'last_error' => mb_substr($reason, 0, 500)]);
	}

	/**
	 * Szybkie sprawdzenie (bez pobierania zadań): token, konto, dostęp do workspace i każdej wybranej listy.
	 */
	public function test(ClickUpConnection $connection): array
	{
		$config = $connection->settings();
		$report = ['ok' => false, 'account' => null, 'workspace' => null, 'workspace_ok' => null, 'lists' => [], 'error' => null, 'reconnect' => false];

		try {
			$this->call($connection, function (ClickUpClient $api) use (&$report, $config, $connection) {
				$user = $api->user();
				$teams = $this->teamsList($api->teams());
				$report['account'] = (string) ($user['username'] ?? $user['email'] ?? '');
				$connection->update(['clickup_username' => $report['account'] ?: $connection->clickup_username, 'authorized_teams' => $teams, 'last_checked_at' => now(), 'last_error' => null]);

				if ($config['team_id'] !== '') {
					$team = collect($teams)->firstWhere('id', $config['team_id']);
					$report['workspace'] = $team['name'] ?? $config['team_name'];
					$report['workspace_ok'] = $team !== null;
				}

				foreach ($config['lists'] as $list) {
					try {
						$api->list((string) $list['id']);
						$report['lists'][] = ['id' => $list['id'], 'name' => $list['name'], 'ok' => true, 'error' => null];
					} catch (IntegrationException $e) {
						if ($e->getCode() === 401) {
							throw $e;
						}

						$report['lists'][] = ['id' => $list['id'], 'name' => $list['name'], 'ok' => false, 'error' => $e->getMessage()];
					}
				}
			});

			$report['ok'] = $report['workspace_ok'] !== false && ! in_array(false, array_column($report['lists'], 'ok'), true);

			if ($report['workspace_ok'] === false) {
				$report['error'] = 'Brak dostępu do wybranego Workspace — autoryzuj go ponownie w ClickUp („Połącz ponownie”) albo wybierz inny.';
			}
		} catch (IntegrationException $e) {
			$report['error'] = $e->getMessage();
			$report['reconnect'] = $e->getCode() === 401;
			$connection->update(['last_checked_at' => now(), 'last_error' => mb_substr($e->getMessage(), 0, 500)]);
		}

		return $report;
	}

	/**
	 * Opcje konfiguracji z konta użytkownika: workspace’y, przestrzenie, listy (z folderów i bez), pola.
	 */
	public function options(ClickUpConnection $connection, string $level, string $id): array
	{
		return $this->call($connection, fn (ClickUpClient $api) => match ($level) {
			'teams' => $this->teamsList($api->teams()),
			'spaces' => array_map(fn ($s) => ['id' => (string) $s['id'], 'name' => (string) $s['name']], $api->spaces($id)),
			'lists' => array_map(fn ($l) => ['id' => $l['id'], 'name' => $l['name'], 'folder' => $l['folder'], 'label' => ($l['folder'] !== '' ? $l['folder'] : 'bez folderu') . ' / ' . $l['name']], $api->lists($id)),
			'fields' => array_values(array_map(fn ($f) => ['id' => 'cf:' . $f['id'], 'name' => (string) $f['name'], 'type' => (string) ($f['type'] ?? '')], $api->fields($id))),
			default => throw new IntegrationException('Nieznany rodzaj opcji.'),
		});
	}

	/**
	 * Zapis konfiguracji użytkownika (walidacja kształtu; listy muszą należeć do autoryzowanego konta —
	 * dostęp sprawdza synchronizacja i „Sprawdź połączenie”).
	 *
	 * @return array<string, string> błędy
	 */
	public function saveConfig(ClickUpConnection $connection, array $input): array
	{
		$id = fn ($v) => preg_match('/^[A-Za-z0-9_-]{1,40}$/', (string) $v) ? (string) $v : '';
		$text = fn ($v, $max = 190) => mb_substr(trim(wp_strip_all_tags((string) $v)), 0, $max);
		$errors = [];

		$lists = [];

		foreach (array_values((array) ($input['lists'] ?? [])) as $list) {
			if (is_array($list) && ($listId = $id($list['id'] ?? '')) !== '' && ! isset($lists[$listId])) {
				$lists[$listId] = ['id' => $listId, 'name' => $text($list['name'] ?? $listId)];
			}
		}

		$mapping = [];
		$labels = [];

		foreach (array_keys(ClickUpMapper::TARGETS) as $target) {
			$source = (string) ($input['mapping'][$target] ?? '');
			$mapping[$target] = preg_match('/^(name|cf:[A-Za-z0-9_-]{1,64})?$/', $source) ? $source : '';

			if (str_starts_with($mapping[$target], 'cf:')) {
				$labels[$mapping[$target]] = $text($input['field_labels'][$mapping[$target]] ?? $mapping[$target], 120);
			}
		}

		$config = [
			'team_id' => $id($input['team_id'] ?? ''),
			'team_name' => $text($input['team_name'] ?? ''),
			'space_id' => $id($input['space_id'] ?? ''),
			'space_name' => $text($input['space_name'] ?? ''),
			'lists' => array_slice(array_values($lists), 0, 20),
			'mapping' => $mapping,
			'field_labels' => $labels,
		];

		if ($config['team_id'] === '') {
			$errors['team_id'] = 'Wybierz Workspace.';
		} elseif (! collect((array) $connection->authorized_teams)->contains('id', $config['team_id'])) {
			$errors['team_id'] = 'Ten Workspace nie jest autoryzowany dla Twojego połączenia.';
		}

		if ($config['lists'] === []) {
			$errors['lists'] = 'Wybierz co najmniej jedną listę z klientami.';
		}

		if ($mapping['company_name'] === '') {
			$errors['mapping.company_name'] = 'Wskaż pole z nazwą firmy.';
		}

		if ($errors) {
			return $errors;
		}

		$before = $connection->settings();
		$connection->update(['config' => $config]);
		$index = DB::table('gst_clickup_index')->where('user_id', $connection->user_id);

		// Zmiana zakresu: indeks dopasowujemy od razu (klienci Guesstimate i oferty nie są ruszane), a pełną
		// synchronizację nowego zakresu uruchamia kontroler (ClickUpSync::restart).
		if ($before['team_id'] !== $config['team_id']) {
			$index->delete();
			$connection->update(['list_state' => null, 'full_synced_at' => null, 'synced_count' => 0]);
		} elseif (array_column($before['lists'], 'id') !== array_column($config['lists'], 'id') || $before['mapping'] !== $config['mapping']) {
			// Wpisy list odznaczonych znikają; mapowanie przelicza pełna synchronizacja (wyszukiwarka działa dalej).
			$index->whereNotIn('list_id', array_column($config['lists'], 'id'))->delete();
			$connection->update(['full_synced_at' => null, 'synced_count' => DB::table('gst_clickup_index')->where('user_id', $connection->user_id)->count()]);
		}

		return [];
	}

	/**
	 * Rozłączenie: usuwa token i prywatny indeks użytkownika. Klienci zaimportowani do Guesstimate zostają.
	 * ClickUp nie udostępnia endpointu unieważniania tokenu — dostęp aplikacji można dodatkowo odebrać
	 * w ClickUp (Settings → Apps).
	 */
	public function disconnect(int $userId): void
	{
		DB::transaction(function () use ($userId) {
			DB::table('gst_clickup_index')->where('user_id', $userId)->delete();
			ClickUpConnection::where('user_id', $userId)->delete();
		});
		ClickUpSync::unlock($userId);
	}

	private function teamsList(array $teams): array
	{
		return array_values(array_map(fn ($t) => ['id' => (string) ($t['id'] ?? ''), 'name' => (string) ($t['name'] ?? '')], $teams));
	}

	/**
	 * Podpowiedź z poprzedniej (wspólnej) integracji: listy i mapowanie, jeśli użytkownik autoryzował
	 * ten sam workspace. Stary token nie jest używany.
	 */
	private function legacyConfig(array $teams): ?array
	{
		$legacy = (array) $this->settings->get('clickup');

		if (empty($legacy['team_id']) || empty($legacy['list_ids']) || ! collect($teams)->contains('id', (string) $legacy['team_id'])) {
			return null;
		}

		$names = (array) ($legacy['list_names'] ?? []);

		return [
			'team_id' => (string) $legacy['team_id'],
			'team_name' => (string) (collect($teams)->firstWhere('id', (string) $legacy['team_id'])['name'] ?? ''),
			'space_id' => (string) ($legacy['space_id'] ?? ''),
			'space_name' => '',
			'lists' => array_map(fn ($id) => ['id' => (string) $id, 'name' => (string) ($names[$id] ?? $id)], array_values((array) $legacy['list_ids'])),
			'mapping' => (array) ($legacy['mapping'] ?? []),
			'field_labels' => [],
			'from_legacy' => true,
		];
	}

	private static function context(int $userId): string
	{
		return 'clickup-oauth-user-' . $userId;
	}
}
