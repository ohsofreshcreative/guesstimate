<?php

namespace App\Guesstimate\Integrations\ClickUp;

use App\Guesstimate\Integrations\IntegrationException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Silnik synchronizacji firm z ClickUp do prywatnego indeksu użytkownika — ten sam dla przycisku
 * „Synchronizuj teraz”, harmonogramu (WP-CLI / WP-Cron) i pierwszej synchronizacji po konfiguracji.
 *
 * Tryby:
 * - `incremental` — dla każdej listy tylko zadania zmienione po znaczniku listy (`date_updated_gt`, margines
 *   MARGIN_MS). Aktualizuje / dodaje wiersze indeksu, niczego nie usuwa.
 * - `full` — wszystkie zadania wybranych list; po kompletnym przebiegu usuwa z indeksu wpisy, których nie ma
 *   już w listach (zadanie usunięte albo przeniesione poza wybrane listy). Wykonywany raz na dobę, po zmianie
 *   konfiguracji i dla list bez znacznika.
 *
 * Partia = jedna strona API (do 100 zadań) jednej listy; postęp zapisywany po każdej partii. Znacznik listy
 * (`list_state.lists[id].mark_ms` = czas startu przebiegu) przesuwa się dopiero po poprawnym pobraniu całej
 * listy. Usuwanie nieaktualnych wpisów tylko dla list pobranych w całości (bez błędów i bez limitu stron).
 *
 * Odporność: blokada per użytkownik (add_option, wygasa po 90 s), 429 → `waiting` 60 s, sieć / timeout / 5xx →
 * do 3 prób z przerwą, potem `error` (harmonogram wznawia od tej samej partii), 401 → połączenie wymaga ponownej
 * autoryzacji, 403 (Workspace) → błąd bez zrywania połączenia. Przebieg zastąpiony nowszym (zmiana konfiguracji)
 * kończy się bez zapisu.
 */
class ClickUpSync
{
	public const HOOK = 'gst_clickup_sync';
	public const LOCK = 'gst_cu_sync_lock_';
	public const ACTIVE = ['queued', 'running', 'waiting'];
	public const INTERVAL = 15 * 60;            // automatyczna synchronizacja co 15 min
	public const FULL_EVERY = 24 * 3600;        // pełna synchronizacja raz na dobę
	public const MARGIN_MS = 5 * 60 * 1000;     // margines znacznika przyrostowego
	private const MAX_PAGES = 100;
	private const MAX_ATTEMPTS = 3;
	private const STALE_AFTER = 20;             // s bez postępu → partię wykona odpytywanie statusu
	private const QUEUED_STALE_AFTER = 3;       // s w kolejce bez startu WP-Cron
	private const LOCK_TTL = 90;

	public function __construct(private ClickUpAccount $account)
	{
	}

	public static function register(): void
	{
		add_action(self::HOOK, fn ($userId) => app(self::class)->cron((int) $userId), 10, 1);
	}

	public function cron(int $userId): void
	{
		if ($connection = ClickUpConnection::forUser($userId)) {
			$this->step($connection, 20);
		}
	}

	/**
	 * Tryb, którego wymaga połączenie: pełny (brak pełnej synchronizacji w ciągu doby, zmieniona konfiguracja,
	 * lista bez znacznika) albo przyrostowy.
	 */
	public function modeFor(ClickUpConnection $connection): string
	{
		$marks = (array) $connection->list_state;

		if (! $connection->full_synced_at || $connection->full_synced_at->lt(now()->subSeconds(self::FULL_EVERY))
			|| ($marks['lists_key'] ?? '') !== $this->listsKey($connection)) {
			return 'full';
		}

		foreach ($connection->listIds() as $listId) {
			if (empty($marks['lists'][$listId]['mark_ms'])) {
				return 'full';
			}
		}

		return 'incremental';
	}

	/**
	 * Rozpoczęcie (albo wznowienie po błędzie) synchronizacji. Trwająca synchronizacja nie jest uruchamiana drugi raz
	 * (chyba że $restart — zmiana konfiguracji zastępuje bieżący przebieg).
	 *
	 * @param  string|null  $mode  full | incremental | null (automatycznie)
	 * @param  string  $trigger  manual | auto | config
	 */
	public function start(ClickUpConnection $connection, ?string $mode = null, string $trigger = 'manual', bool $restart = false): array
	{
		if ($missing = array_values(array_filter($connection->missing(), fn ($m) => $m !== 'Zsynchronizuj firmy.'))) {
			throw new IntegrationException(implode(' ', $missing));
		}

		$fresh = $connection->sync_heartbeat_at && $connection->sync_heartbeat_at->gt(now()->subMinutes(2));

		if (! $restart && in_array($connection->sync_status, self::ACTIVE, true) && $fresh) {
			return $this->status($connection, false);
		}

		$state = (array) $connection->sync_state;
		$key = $this->listsKey($connection);
		// Wznowienie po błędzie (albo porzuconego przebiegu): od zapisanej partii tego samego przebiegu.
		$resume = ! $restart && in_array($connection->sync_status, ['error', ...self::ACTIVE], true)
			&& ! empty($state['run']) && ($state['lists_key'] ?? '') === $key && ($mode === null || $mode === ($state['mode'] ?? null));

		if (! $resume) {
			$mode ??= $this->modeFor($connection);
			$marks = (array) (($connection->list_state['lists'] ?? null) ?: []);
			$started = (int) (microtime(true) * 1000);
			$state = [
				'run' => $started,
				'mode' => $mode,
				'trigger' => $trigger,
				'started_ms' => $started,
				'lists_key' => $key,
				'list_index' => 0,
				'page' => 0,
				'processed' => 0,
				'lists' => array_map(function ($l) use ($mode, $marks) {
					$mark = (int) ($marks[$l['id']]['mark_ms'] ?? 0);

					return [
						'id' => $l['id'],
						'name' => $l['name'],
						'tasks' => 0,
						'pages' => 0,
						'done' => false,
						// Lista bez znacznika w przebiegu przyrostowym jest pobierana w całości (bez usuwania).
						'since_ms' => $mode === 'incremental' && $mark > 0 ? max(0, $mark - self::MARGIN_MS) : null,
					];
				}, $connection->settings()['lists']),
			];
		}

		$state['attempts'] = 0;
		$state['retry_at'] = null;
		$connection->update([
			'sync_status' => 'queued',
			'sync_state' => $state,
			'sync_error' => null,
			'sync_started_at' => $resume ? ($connection->sync_started_at ?? now()) : now(),
			'sync_heartbeat_at' => now(),
		]);
		$this->schedule($connection->user_id);

		return $this->status($connection->fresh(), false);
	}

	/**
	 * Wykonuje partie synchronizacji w limicie czasu. Zwraca true, gdy zostało coś do zrobienia.
	 */
	public function step(ClickUpConnection $connection, int $budget): bool
	{
		if (! in_array($connection->sync_status, self::ACTIVE, true) || ! $this->lock($connection->user_id)) {
			return false;
		}

		$deadline = microtime(true) + $budget;

		try {
			$connection->refresh();
			$state = (array) $connection->sync_state;

			if (! in_array($connection->sync_status, self::ACTIVE, true)) {
				return false;
			}

			if ($connection->sync_status === 'waiting' && ! empty($state['retry_at']) && $state['retry_at'] > time()) {
				return true;
			}

			$connection->update(['sync_status' => 'running', 'sync_heartbeat_at' => now()]);

			while (microtime(true) < $deadline) {
				$list = $state['lists'][$state['list_index']] ?? null;

				if ($list === null) {
					$this->finish($connection, $state);

					return false;
				}

				$since = isset($list['since_ms']) ? (int) $list['since_ms'] : null;

				try {
					$page = $this->account->call($connection, fn (ClickUpClient $api) => $api->tasksPage((string) $list['id'], (int) $state['page'], $since));
				} catch (IntegrationException $e) {
					return $this->failed($connection, $state, $e);
				}

				// Konfiguracja zmieniona w trakcie (nowy przebieg) — ten przebieg niczego już nie zapisuje.
				if ($this->superseded($connection, $state)) {
					return false;
				}

				$this->store($connection, $list, $page['tasks'], (int) $state['run']);
				$i = $state['list_index'];
				$state['lists'][$i]['tasks'] += count($page['tasks']);
				$state['lists'][$i]['pages'] = (int) $state['page'] + 1;
				$state['processed'] = (int) ($state['processed'] ?? 0) + count($page['tasks']);
				$state['attempts'] = 0;
				$state['retry_at'] = null;

				if ($page['last'] || (int) $state['page'] + 1 >= self::MAX_PAGES) {
					$state['lists'][$i]['done'] = true;
					$state['lists'][$i]['complete'] = $page['last'];

					if ($page['last']) {
						$this->markList($connection, $state, $state['lists'][$i]);
					}

					$state['list_index']++;
					$state['page'] = 0;
				} else {
					$state['page']++;
				}

				$connection->update(['sync_state' => $state, 'sync_heartbeat_at' => now(), 'sync_error' => null]);
			}

			$this->schedule($connection->user_id);

			return true;
		} catch (Throwable $e) {
			$connection->update(['sync_status' => 'error', 'sync_error' => 'Nieoczekiwany błąd synchronizacji. Spróbuj ponownie.', 'next_sync_at' => now()->addSeconds(self::INTERVAL)]);
			// Bez danych wrażliwych: klasa i komunikat (token nigdy nie jest częścią komunikatu).
			error_log('Guesstimate ClickUp sync: ' . get_class($e) . ': ' . mb_substr($e->getMessage(), 0, 300));

			return false;
		} finally {
			self::unlock($connection->user_id);
		}
	}

	/**
	 * Stan synchronizacji do interfejsu. Gdy partie stoją (brak WP-Cron), wykonuje jedną krótką partię.
	 */
	public function status(ClickUpConnection $connection, bool $advance = true): array
	{
		$staleAfter = $connection->sync_status === 'queued' ? self::QUEUED_STALE_AFTER : self::STALE_AFTER;

		if ($advance && in_array($connection->sync_status, self::ACTIVE, true)
			&& (! $connection->sync_heartbeat_at || $connection->sync_heartbeat_at->lt(now()->subSeconds($staleAfter)))) {
			$this->step($connection, 8);
			$connection->refresh();
		}

		$state = (array) $connection->sync_state;
		$current = $state['lists'][$state['list_index'] ?? 0] ?? null;
		$active = in_array($connection->sync_status, self::ACTIVE, true);
		$date = fn ($at) => $at ? wp_date('d.m.Y H:i', $at->getTimestamp()) : null;

		return [
			'status' => $connection->sync_status,
			'active' => $active,
			'mode' => $state['mode'] ?? null,
			'current_list' => $active ? ($current['name'] ?? null) : null,
			'current_page' => $active && $current ? (int) ($state['page'] ?? 0) + 1 : null,
			'processed' => (int) ($state['processed'] ?? 0),
			'companies' => (int) DB::table('gst_clickup_index')->where('user_id', $connection->user_id)->count(),
			'lists' => array_map(fn ($l) => ['name' => $l['name'], 'tasks' => (int) $l['tasks'], 'done' => (bool) $l['done'], 'complete' => (bool) ($l['complete'] ?? $l['done'])], (array) ($state['lists'] ?? [])),
			'retry_at' => ! empty($state['retry_at']) ? wp_date('H:i', (int) $state['retry_at']) : null,
			'synced_at' => $date($connection->synced_at),
			'full_synced_at' => $date($connection->full_synced_at),
			'next_sync_at' => $connection->isConnected() && ! $active ? $date($connection->next_sync_at ?? now()) : null,
			'synced_count' => (int) $connection->synced_count,
			'error' => $connection->sync_error,
		];
	}

	/**
	 * Po zmianie konfiguracji: pełna synchronizacja zastępująca bieżący przebieg.
	 */
	public function restart(ClickUpConnection $connection): array
	{
		return $this->start($connection, 'full', 'config', true);
	}

	private function superseded(ClickUpConnection $connection, array $state): bool
	{
		$current = json_decode((string) DB::table('gst_clickup_connections')->where('id', $connection->id)->value('sync_state'), true);

		return (int) ($current['run'] ?? 0) !== (int) $state['run'];
	}

	/**
	 * Znacznik listy: czas startu przebiegu (zmiany wykonane w trakcie pobierania obejmie następny przebieg dzięki
	 * marginesowi). Zapisywany tylko po kompletnym pobraniu listy.
	 */
	private function markList(ClickUpConnection $connection, array $state, array $list): void
	{
		$marks = (array) $connection->list_state;
		$entry = (array) ($marks['lists'][$list['id']] ?? []);
		$entry['mark_ms'] = (int) $state['started_ms'];

		if (($state['mode'] ?? '') === 'full') {
			$entry['full_at'] = (int) $state['started_ms'];
		}

		$marks['lists'][$list['id']] = $entry;
		$connection->update(['list_state' => $marks]);
	}

	private function store(ClickUpConnection $connection, array $list, array $tasks, int $run): void
	{
		$config = $connection->settings();
		$rows = [];

		foreach ($tasks as $task) {
			$mapped = ClickUpMapper::map($task, $config['mapping']);

			if ($mapped['company_name'] === null || empty($task['id'])) {
				continue;
			}

			$rows[(string) $task['id']] = [
				'user_id' => $connection->user_id,
				'task_id' => mb_substr((string) $task['id'], 0, 40),
				'team_id' => $config['team_id'],
				'list_id' => (string) $list['id'],
				'list_name' => mb_substr((string) $list['name'], 0, 190),
				'status' => mb_substr((string) ($task['status']['status'] ?? ''), 0, 100) ?: null,
				'company_name' => $mapped['company_name'],
				'search_name' => mb_substr(ClickUpDirectory::fold($mapped['company_name']), 0, 190),
				'search_other' => mb_substr(ClickUpDirectory::fold(implode(' ', array_filter([$mapped['nip'], $mapped['email'], $mapped['contact_name'], $mapped['website_url']]))), 0, 600) ?: null,
				'website_url' => $mapped['website_url'] ? mb_substr($mapped['website_url'], 0, 255) : null,
				'contact_name' => $mapped['contact_name'],
				'email' => $mapped['email'],
				'phone' => $mapped['phone'],
				'nip' => $mapped['nip'] ? mb_substr($mapped['nip'], 0, 40) : null,
				'run' => $run,
				'updated_at' => now(),
			];
		}

		if ($rows) {
			// Klucz (user_id, task_id): ten sam identyfikator ClickUp aktualizuje istniejący wpis — bez duplikatów.
			DB::table('gst_clickup_index')->upsert(array_values($rows), ['user_id', 'task_id'], ['team_id', 'list_id', 'list_name', 'status', 'company_name', 'search_name', 'search_other', 'website_url', 'contact_name', 'email', 'phone', 'nip', 'run', 'updated_at']);
		}
	}

	private function finish(ClickUpConnection $connection, array $state): void
	{
		DB::transaction(function () use ($connection, $state) {
			$full = ($state['mode'] ?? 'full') === 'full';
			$complete = array_values(array_map(fn ($l) => (string) $l['id'], array_filter($state['lists'], fn ($l) => ! empty($l['complete']))));
			$allComplete = count($complete) === count($state['lists']);
			$index = fn () => DB::table('gst_clickup_index')->where('user_id', $connection->user_id);

			if ($full) {
				// Uzgodnienie: wpisy list pobranych w całości, których nie było w tym przebiegu — zadanie usunięte
				// albo przeniesione poza wybrane listy. Listy niekompletne (limit stron) zostają bez zmian.
				if ($complete) {
					$index()->where('run', '!=', (int) $state['run'])->whereIn('list_id', $complete)->delete();
				}

				if ($allComplete) {
					$index()->whereNotIn('list_id', array_column($state['lists'], 'id'))->delete();
				}
			}

			$marks = (array) $connection->fresh()->list_state;
			$marks['lists_key'] = $state['lists_key'];

			$connection->update([
				'sync_status' => 'done',
				'sync_state' => $state,
				'list_state' => $marks,
				'synced_at' => now(),
				'full_synced_at' => $full && $allComplete ? now() : $connection->full_synced_at,
				'next_sync_at' => now()->addSeconds(self::INTERVAL),
				'synced_count' => $index()->count(),
				'sync_error' => null,
				'sync_heartbeat_at' => now(),
			]);
		});
	}

	private function failed(ClickUpConnection $connection, array $state, IntegrationException $e): bool
	{
		$next = now()->addSeconds(self::INTERVAL);

		if ($e->getCode() === 401 || $e->getCode() === 403) {
			// 401: ClickUpAccount::call() oznaczył już połączenie jako wymagające ponownej autoryzacji.
			$connection->update(['sync_status' => 'error', 'sync_state' => $state, 'sync_error' => $e->getMessage(), 'next_sync_at' => $next]);

			return false;
		}

		if ($e->getCode() === 429) {
			$state['retry_at'] = time() + 60;
			$connection->update(['sync_status' => 'waiting', 'sync_state' => $state, 'sync_error' => 'ClickUp: przekroczono limit zapytań API — synchronizacja wznowi się automatycznie za minutę.', 'sync_heartbeat_at' => now()]);
			$this->schedule($connection->user_id, 61);

			return true;
		}

		// Sieć, timeout, 5xx i inne: ponowienie tej samej partii z rosnącą przerwą.
		$state['attempts'] = (int) ($state['attempts'] ?? 0) + 1;

		if ($state['attempts'] >= self::MAX_ATTEMPTS) {
			$connection->update(['sync_status' => 'error', 'sync_state' => $state, 'sync_error' => $e->getMessage() . ' Kliknij „Synchronizuj teraz”, aby ponowić od miejsca przerwania (harmonogram ponowi automatycznie).', 'next_sync_at' => $next]);

			return false;
		}

		$state['retry_at'] = time() + 15 * $state['attempts'];
		$connection->update(['sync_status' => 'waiting', 'sync_state' => $state, 'sync_error' => $e->getMessage() . ' Ponowienie za chwilę.', 'sync_heartbeat_at' => now()]);
		$this->schedule($connection->user_id, 15 * $state['attempts'] + 1);

		return true;
	}

	/**
	 * Blokada per użytkownik: add_option jest atomowe (unikalny klucz w wp_options), więc dwa procesy
	 * (WP-Cron, WP-CLI, odpytywanie statusu) nie wykonają partii jednocześnie. Blokada starsza niż LOCK_TTL
	 * jest uznawana za porzuconą (np. przerwany proces).
	 */
	private function lock(int $userId): bool
	{
		$key = self::LOCK . $userId;

		if (add_option($key, time(), '', false)) {
			return true;
		}

		wp_cache_delete($key, 'options');

		if ((int) get_option($key) < time() - self::LOCK_TTL) {
			delete_option($key);

			return add_option($key, time(), '', false);
		}

		return false;
	}

	public static function unlock(int $userId): void
	{
		delete_option(self::LOCK . $userId);
	}

	private function schedule(int $userId, int $delay = 0): void
	{
		if (! wp_next_scheduled(self::HOOK, [$userId])) {
			wp_schedule_single_event(time() + $delay, self::HOOK, [$userId]);
		}

		if ($delay === 0 && function_exists('spawn_cron') && ! (defined('WP_CLI') && WP_CLI)) {
			spawn_cron();
		}
	}

	public function listsKey(ClickUpConnection $connection): string
	{
		$config = $connection->settings();

		return md5(wp_json_encode([$config['team_id'], array_column($config['lists'], 'id'), $config['mapping']]));
	}
}
