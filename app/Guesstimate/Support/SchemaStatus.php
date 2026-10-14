<?php

namespace App\Guesstimate\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Sprawdza, czy wszystkie migracje aplikacji zostały uruchomione (komunikat dla administratora).
 */
final class SchemaStatus
{
	/**
	 * @return list<string> nazwy oczekujących migracji
	 */
	public static function pending(): array
	{
		$files = array_map(fn ($file) => basename($file, '.php'), glob(database_path('migrations/*.php')) ?: []);

		try {
			$ran = Schema::hasTable('migrations') ? DB::table('migrations')->pluck('migration')->all() : [];
		} catch (Throwable) {
			return $files;
		}

		return array_values(array_diff($files, $ran));
	}
}
