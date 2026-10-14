<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Automatyczna synchronizacja ClickUp (znaczniki przyrostowe per lista, pełna synchronizacja dobowa,
 * termin następnej synchronizacji) oraz informacja, kto usunął klienta (soft delete już istnieje).
 * Wyłącznie nowe, puste kolumny — istniejące dane pozostają bez zmian.
 */
return new class extends Migration
{
	public function up(): void
	{
		Schema::table('gst_clickup_connections', function (Blueprint $table) {
			// {lists_key, lists: {list_id: {mark_ms, full_at}}} — czas ostatniej poprawnie zakończonej synchronizacji listy
			$table->json('list_state')->nullable()->after('sync_state');
			$table->timestamp('full_synced_at')->nullable()->after('synced_at');
			$table->timestamp('next_sync_at')->nullable()->after('full_synced_at');
			$table->index('next_sync_at');
		});

		Schema::table('gst_clients', function (Blueprint $table) {
			$table->unsignedBigInteger('deleted_by')->nullable()->after('deleted_at');
		});
	}

	public function down(): void
	{
		Schema::table('gst_clients', function (Blueprint $table) {
			$table->dropColumn('deleted_by');
		});

		Schema::table('gst_clickup_connections', function (Blueprint $table) {
			$table->dropIndex(['next_sync_at']);
			$table->dropColumn(['list_state', 'full_synced_at', 'next_sync_at']);
		});
	}
};
