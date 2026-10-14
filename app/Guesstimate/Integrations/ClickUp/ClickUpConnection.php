<?php

namespace App\Guesstimate\Integrations\ClickUp;

use App\Guesstimate\Support\Model;

/**
 * Połączenie ClickUp jednego użytkownika WordPress (OAuth). Token jest zaszyfrowany i nie jest
 * serializowany do tablic/JSON ($hidden).
 */
class ClickUpConnection extends Model
{
	public const CONNECTED = 'connected';
	public const RECONNECT = 'reconnect_required';

	protected $table = 'gst_clickup_connections';

	protected $hidden = ['token_ciphertext'];

	protected $casts = [
		'user_id' => 'integer',
		'authorized_teams' => 'array',
		'config' => 'array',
		'sync_state' => 'array',
		'list_state' => 'array',
		'full_synced_at' => 'datetime',
		'next_sync_at' => 'datetime',
		'synced_count' => 'integer',
		'connected_at' => 'datetime',
		'last_checked_at' => 'datetime',
		'sync_started_at' => 'datetime',
		'sync_heartbeat_at' => 'datetime',
		'synced_at' => 'datetime',
	];

	public static function forUser(int $userId): ?self
	{
		return $userId > 0 ? self::where('user_id', $userId)->first() : null;
	}

	/**
	 * Konfiguracja użytkownika z wartościami domyślnymi (mapowanie: Nazwa firmy → Nazwa zadania).
	 */
	public function settings(): array
	{
		$config = array_replace(['team_id' => '', 'team_name' => '', 'space_id' => '', 'space_name' => '', 'lists' => [], 'mapping' => [], 'field_labels' => []], (array) $this->config);
		$config['mapping'] = array_replace(array_fill_keys(array_keys(ClickUpMapper::TARGETS), ''), ['company_name' => 'name'], (array) $config['mapping']);
		$config['lists'] = array_values(array_filter((array) $config['lists'], fn ($l) => is_array($l) && ! empty($l['id'])));

		return $config;
	}

	public function listIds(): array
	{
		return array_map(fn ($l) => (string) $l['id'], $this->settings()['lists']);
	}

	public function isConnected(): bool
	{
		return $this->status === self::CONNECTED && $this->token_ciphertext !== null;
	}

	/**
	 * Czego brakuje do wyszukiwania (dla komunikatów w interfejsie).
	 *
	 * @return list<string>
	 */
	public function missing(): array
	{
		$config = $this->settings();
		$missing = [];

		if (! $this->isConnected()) {
			$missing[] = 'Połączenie z ClickUp wygasło lub zostało cofnięte. Połącz ponownie.';
		}

		if ($config['team_id'] === '') {
			$missing[] = 'Wybierz Workspace.';
		}

		if ($config['lists'] === []) {
			$missing[] = 'Wybierz listy z klientami.';
		}

		if ($config['mapping']['company_name'] === '') {
			$missing[] = 'Wskaż pole z nazwą firmy.';
		}

		if ($this->synced_at === null && ! in_array($this->sync_status, ClickUpSync::ACTIVE, true)) {
			$missing[] = 'Zsynchronizuj firmy.';
		}

		return $missing;
	}
}
