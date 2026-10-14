<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kosz ofert: kto przeniósł ofertę do kosza (data jest w istniejącej kolumnie deleted_at — soft delete).
 */
return new class extends Migration
{
	public function up(): void
	{
		Schema::table('gst_offers', function (Blueprint $table) {
			$table->unsignedBigInteger('deleted_by')->nullable()->after('deleted_at');
			$table->index('deleted_at');
		});
	}

	public function down(): void
	{
		Schema::table('gst_offers', function (Blueprint $table) {
			$table->dropIndex(['deleted_at']);
			$table->dropColumn('deleted_by');
		});
	}
};
