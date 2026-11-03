<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tło stron umowy (np. papier firmowy) z biblioteki mediów WP: ustawiane w szablonie, a przy zatwierdzeniu
 * kopiowane do wersji umowy, żeby zatwierdzona umowa nie zmieniała wyglądu. Wyłącznie nowe, puste kolumny.
 */
return new class extends Migration
{
	public function up(): void
	{
		Schema::table('gst_contract_templates', function (Blueprint $table) {
			$table->unsignedBigInteger('background_id')->nullable()->after('description');
		});

		Schema::table('gst_contract_versions', function (Blueprint $table) {
			$table->unsignedBigInteger('background_id')->nullable()->after('data_snapshot');
		});
	}

	public function down(): void
	{
		Schema::table('gst_contract_versions', function (Blueprint $table) {
			$table->dropColumn('background_id');
		});

		Schema::table('gst_contract_templates', function (Blueprint $table) {
			$table->dropColumn('background_id');
		});
	}
};
