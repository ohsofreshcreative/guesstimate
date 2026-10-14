<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ustawienia aplikacji (klucz → wartość JSON). Tabela z prefiksem WP: wp_gst_settings.
 * Wartości domyślne (stawki, zakresy UX, etapy) wprowadzi etap 2.
 */
return new class extends Migration
{
	public function up(): void
	{
		Schema::create('gst_settings', function (Blueprint $table) {
			$table->id();
			$table->string('key', 100)->unique();
			$table->json('value');
			$table->unsignedBigInteger('updated_by')->nullable();
			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('gst_settings');
	}
};
