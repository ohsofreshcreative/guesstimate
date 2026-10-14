<?php

namespace App\Guesstimate\Console;

use App\Guesstimate\Integrations\ClickUp\ClickUpConnection;
use App\Guesstimate\Integrations\ClickUp\ClickUpScheduler;
use App\Guesstimate\Integrations\ClickUp\ClickUpSync;
use WP_CLI;

/**
 * Guesstimate — synchronizacja ClickUp z wiersza poleceń (systemowy cron, diagnostyka).
 *
 * ## EXAMPLES
 *
 *     wp guesstimate clickup sync-due
 *     wp guesstimate clickup sync-due --time=50
 *     wp guesstimate clickup status
 *     wp guesstimate clickup sync --user-id=3 --full
 */
class ClickUpCommand
{
	/**
	 * Uruchamia należne synchronizacje (co 15 min przyrostowa, raz na dobę pełna) i wykonuje zakolejkowane partie.
	 *
	 * ## OPTIONS
	 *
	 * [--time=<seconds>]
	 * : Limit czasu przebiegu (domyślnie 240 s — mniej niż odstęp crona co 5 min).
	 * ---
	 * default: 240
	 * ---
	 *
	 * @subcommand sync-due
	 */
	public function syncDue(array $args, array $assoc): void
	{
		$time = max(10, min(840, (int) ($assoc['time'] ?? 240)));
		$report = app(ClickUpScheduler::class)->runDue($time);

		if ($report['skipped']) {
			WP_CLI::log('Poprzedni przebieg harmonogramu jeszcze trwa — pominięto.');

			return;
		}

		foreach ($report['errors'] as $userId => $message) {
			WP_CLI::warning(sprintf('Użytkownik %d: %s', $userId, $message));
		}

		WP_CLI::success(sprintf('Rozpoczęte: %d, wykonywane partie: %d użytkowników, błędy: %d.', count($report['started']), count($report['worked']), count($report['errors'])));
	}

	/**
	 * Stan synchronizacji wszystkich połączeń (bez tokenów).
	 */
	public function status(): void
	{
		$sync = app(ClickUpSync::class);
		$rows = ClickUpConnection::query()->orderBy('user_id')->get()->map(function (ClickUpConnection $c) use ($sync) {
			$s = $sync->status($c, false);

			return [
				'user' => $c->user_id,
				'connection' => $c->status,
				'sync' => $s['status'],
				'mode' => $s['mode'] ?? '',
				'companies' => $s['companies'],
				'last' => $s['synced_at'] ?? '—',
				'full' => $s['full_synced_at'] ?? '—',
				'next' => $s['next_sync_at'] ?? '—',
				'error' => $s['error'] ?? '',
			];
		})->all();

		WP_CLI\Utils\format_items('table', $rows, ['user', 'connection', 'sync', 'mode', 'companies', 'last', 'full', 'next', 'error']);
	}

	/**
	 * Synchronizacja jednego użytkownika teraz (w tym procesie).
	 *
	 * ## OPTIONS
	 *
	 * --user-id=<id>
	 * : ID użytkownika WordPress.
	 *
	 * [--full]
	 * : Wymuś pełną synchronizację.
	 */
	public function sync(array $args, array $assoc): void
	{
		$connection = ClickUpConnection::forUser((int) ($assoc['user-id'] ?? 0));

		if (! $connection || ! $connection->isConnected()) {
			WP_CLI::error('Brak aktywnego połączenia ClickUp dla tego użytkownika.');
		}

		$sync = app(ClickUpSync::class);
		$sync->start($connection, ! empty($assoc['full']) ? 'full' : null, 'manual');

		for ($i = 0; $i < 60 && $sync->step($connection->fresh(), 20); $i++) {
			$state = $connection->fresh();

			if ($state->sync_status === 'waiting') {
				sleep(5);
			}
		}

		$status = $sync->status($connection->fresh(), false);
		$status['status'] === 'done'
			? WP_CLI::success(sprintf('Zsynchronizowano (%s): %d firm.', $status['mode'], $status['companies']))
			: WP_CLI::warning(sprintf('Stan: %s. %s', $status['status'], $status['error'] ?? ''));
	}
}
