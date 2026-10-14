<?php

namespace App\Guesstimate\Settings;

use Illuminate\Support\Facades\DB;

/**
 * Ustawienia globalne (tabela wp_gst_settings, wartości JSON). Brakujące klucze uzupełniają Defaults.
 */
class SettingsRepository
{
	private ?array $cache = null;

	public function all(): array
	{
		if ($this->cache === null) {
			$stored = DB::table('gst_settings')->pluck('value', 'key')
				->map(fn ($value) => json_decode($value, true))
				->all();

			$this->cache = array_replace(Defaults::all(), $stored);
		}

		return $this->cache;
	}

	public function get(string $key): mixed
	{
		return $this->all()[$key] ?? null;
	}

	public function set(string $key, mixed $value): void
	{
		$now = now();

		DB::table('gst_settings')->updateOrInsert(
			['key' => $key],
			[
				'value' => json_encode($value, JSON_UNESCAPED_UNICODE),
				'updated_by' => get_current_user_id() ?: null,
				'updated_at' => $now,
			]
		);

		$this->cache = null;
	}

	/**
	 * @return array<string, array{label: string, min: string, max: string}>
	 */
	public function uxScopes(): array
	{
		return \App\Guesstimate\Offers\BudgetSettings::uxScopes((array) $this->get('ux_scopes'));
	}

	/**
	 * @return array<string, string> klucz etapu => nazwa
	 */
	public function stageNames(): array
	{
		return array_column($this->get('stages'), 'name', 'key');
	}
}
