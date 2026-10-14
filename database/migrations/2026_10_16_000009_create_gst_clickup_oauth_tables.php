<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ClickUp OAuth 2.0 per użytkownik: połączenia (zaszyfrowany token, konfiguracja list i mapowania, stan
 * synchronizacji) i prywatny indeks firm każdego użytkownika. Klienci dostają kontekst źródła (workspace,
 * kto zaimportował). Stara wspólna konfiguracja (Personal API Token) nie jest usuwana — nie jest już używana;
 * jej listy i mapowanie podpowiadają się użytkownikowi po połączeniu tego samego workspace.
 */
return new class extends Migration
{
	public function up(): void
	{
		Schema::create('gst_clickup_connections', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('user_id')->unique();
			$table->text('token_ciphertext')->nullable();
			$table->string('status', 30)->default('connected'); // connected | reconnect_required
			$table->string('clickup_user_id', 40)->nullable();
			$table->string('clickup_username', 190)->nullable();
			$table->json('authorized_teams')->nullable();
			$table->json('config')->nullable(); // team_id, team_name, space_id, space_name, lists[], mapping, field_labels
			$table->timestamp('connected_at')->nullable();
			$table->timestamp('last_checked_at')->nullable();
			$table->string('last_error', 500)->nullable();
			$table->string('sync_status', 20)->default('never'); // never | queued | running | waiting | done | error
			$table->json('sync_state')->nullable(); // postęp: lista, strona, liczniki, próby
			$table->timestamp('sync_started_at')->nullable();
			$table->timestamp('sync_heartbeat_at')->nullable();
			$table->timestamp('synced_at')->nullable();
			$table->unsignedInteger('synced_count')->default(0);
			$table->string('sync_error', 500)->nullable();
			$table->timestamps();
		});

		Schema::create('gst_clickup_index', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('user_id');
			$table->string('task_id', 40);
			$table->string('team_id', 40)->nullable();
			$table->string('list_id', 40);
			$table->string('list_name', 190)->nullable();
			$table->string('status', 100)->nullable();
			$table->string('company_name', 190);
			$table->string('search_name', 190);
			$table->string('search_other', 600)->nullable();
			$table->string('website_url', 255)->nullable();
			$table->string('contact_name', 190)->nullable();
			$table->string('email', 190)->nullable();
			$table->string('phone', 50)->nullable();
			$table->string('nip', 40)->nullable();
			$table->unsignedBigInteger('run')->default(0);
			$table->timestamp('updated_at')->nullable();
			$table->unique(['user_id', 'task_id']);
			$table->index(['user_id', 'search_name']);
		});

		Schema::table('gst_clients', function (Blueprint $table) {
			$table->string('clickup_team_id', 40)->nullable()->after('clickup_id');
			$table->unsignedBigInteger('clickup_imported_by')->nullable()->after('clickup_team_id');
		});
	}

	public function down(): void
	{
		Schema::table('gst_clients', function (Blueprint $table) {
			$table->dropColumn(['clickup_team_id', 'clickup_imported_by']);
		});
		Schema::dropIfExists('gst_clickup_index');
		Schema::dropIfExists('gst_clickup_connections');
	}
};
