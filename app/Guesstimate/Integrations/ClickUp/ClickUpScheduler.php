<?php

namespace App\Guesstimate\Integrations\ClickUp;

use Throwable;

/**
 * Harmonogram automatycznej synchronizacji ClickUp — planowanie ORAZ wykonywanie zakolejkowanych partii.
 *
 * Wywołania:
 * - systemowy cron → `wp guesstimate clickup sync-due` (zalecane na Hostingerze, co 5 min),
 * - WP-Cron (`gst_clickup_tick` co 5 min) — rezerwa, gdy systemowy cron nie jest skonfigurowany.
 *
 * Jeden przebieg (runDue):
 * 1. Globalna blokada przebiegu (add_option, wygasa po czasie) — dwa crony nie nakładają się.
 * 2. Planowanie: połączenia aktywne (OAuth `connected`, kompletna konfiguracja), bez trwającej synchronizacji,
 *    z `next_sync_at` <= teraz (albo bez terminu) → ClickUpSync::start(tryb automatyczny: przyrostowy / pełny).
 * 3. Wykonywanie: wszystkie trwające synchronizacje partiami, po kolei (round-robin) z limitem czasu na
 *    użytkownika — jeden duży indeks nie blokuje pozostałych. Blokady per użytkownik respektuje ClickUpSync::step.
 * Błąd jednego użytkownika jest izolowany (try/catch per połączenie). Nie wymaga zalogowanego użytkownika.
 */
class ClickUpScheduler
{
	public const TICK = 'gst_clickup_tick';
	public const SCHEDULE = 'gst_five_minutes';
	private const RUNNER_LOCK = 'gst_cu_runner_lock';

	public function __construct(private ClickUpSync $sync)
	{
	}

	public static function register(): void
	{
		add_filter('cron_schedules', function (array $schedules) {
			$schedules[self::SCHEDULE] ??= ['interval' => 300, 'display' => 'Guesstimate: co 5 minut'];

			return $schedules;
		});
		add_action(self::TICK, fn () => app(self::class)->runDue(25));
		add_action('init', function () {
			if (! wp_next_scheduled(self::TICK)) {
				wp_schedule_event(time() + 60, self::SCHEDULE, self::TICK);
			}
		});
	}

	/**
	 * Połączenia, które powinny teraz rozpocząć automatyczną synchronizację.
	 *
	 * @return list<ClickUpConnection>
	 */
	public function due(): array
	{
		return ClickUpConnection::query()
			->where('status', ClickUpConnection::CONNECTED)
			->whereNotNull('token_ciphertext')
			->whereNotIn('sync_status', ClickUpSync::ACTIVE) // trwające (także czekające na ponowienie) kontynuuje work()
			->where(fn ($q) => $q->whereNull('next_sync_at')->orWhere('next_sync_at', '<=', now()))
			->orderBy('next_sync_at')
			->get()
			->filter(fn (ClickUpConnection $c) => $this->configured($c))
			->values()
			->all();
	}

	/**
	 * @return array{skipped: bool, started: list<int>, worked: list<int>, errors: array<int, string>}
	 */
	public function runDue(int $budget = 240, int $perUser = 20): array
	{
		$report = ['skipped' => false, 'started' => [], 'worked' => [], 'errors' => []];

		if (! $this->acquire($budget + 60)) {
			$report['skipped'] = true;

			return $report;
		}

		$deadline = microtime(true) + $budget;

		try {
			foreach ($this->due() as $connection) {
				try {
					$this->sync->start($connection, null, 'auto');
					$report['started'][] = $connection->user_id;
				} catch (Throwable $e) {
					$report['errors'][$connection->user_id] = mb_substr($e->getMessage(), 0, 200);
					$connection->update(['next_sync_at' => now()->addSeconds(ClickUpSync::INTERVAL)]);
				}
			}

			// Round-robin: każda trwająca synchronizacja dostaje kolejne partie, dopóki jest czas i postęp.
			do {
				$progress = false;

				foreach (ClickUpConnection::query()->whereIn('sync_status', ClickUpSync::ACTIVE)->orderBy('sync_heartbeat_at')->get() as $connection) {
					$left = (int) floor($deadline - microtime(true));

					if ($left < 2) {
						break 2;
					}

					$before = json_encode($connection->sync_state) . $connection->sync_status;

					try {
						$this->sync->step($connection, min($perUser, $left));
					} catch (Throwable $e) {
						$report['errors'][$connection->user_id] = mb_substr($e->getMessage(), 0, 200);
					}

					$connection->refresh();

					if (json_encode($connection->sync_state) . $connection->sync_status !== $before) {
						$progress = true;
						$report['worked'][] = $connection->user_id;
					}
				}
			} while ($progress && microtime(true) < $deadline);
		} finally {
			delete_option(self::RUNNER_LOCK);
		}

		$report['worked'] = array_values(array_unique($report['worked']));

		return $report;
	}

	private function configured(ClickUpConnection $connection): bool
	{
		$config = $connection->settings();

		return $config['team_id'] !== '' && $config['lists'] !== [] && ($config['mapping']['company_name'] ?? '') !== '';
	}

	private function acquire(int $ttl): bool
	{
		if (add_option(self::RUNNER_LOCK, time() + $ttl, '', false)) {
			return true;
		}

		wp_cache_delete(self::RUNNER_LOCK, 'options');

		if ((int) get_option(self::RUNNER_LOCK) < time()) {
			delete_option(self::RUNNER_LOCK);

			return add_option(self::RUNNER_LOCK, time() + $ttl, '', false);
		}

		return false;
	}
}
