<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moduł umów. Tylko nowe tabele: oferty, klienci i publikacje pozostają bez zmian.
 *
 * Treść i snapshot są w LONGTEXT, nie w JSON: MySQL normalizuje kolumny JSON (kolejność kluczy, zapis liczb),
 * a `content_hash` musi potwierdzać dokładnie te bajty, które zatwierdzono.
 */
return new class extends Migration
{
	public function up(): void
	{
		Schema::create('gst_contract_templates', function (Blueprint $table) {
			$table->id();
			$table->string('name', 190);
			$table->string('description', 500)->nullable();
			$table->unsignedBigInteger('current_version_id')->nullable();
			$table->unsignedBigInteger('created_by')->nullable();
			$table->timestamp('archived_at')->nullable();
			$table->unsignedBigInteger('archived_by')->nullable();
			$table->timestamps();
			$table->index('archived_at');
		});

		Schema::create('gst_contract_template_versions', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('template_id');
			$table->unsignedInteger('version_no');
			$table->string('status', 20)->default('draft');
			$table->longText('body');
			$table->char('content_hash', 64)->nullable();
			$table->unsignedBigInteger('created_by')->nullable();
			$table->unsignedBigInteger('approved_by')->nullable();
			$table->timestamp('approved_at')->nullable();
			$table->timestamps();
			$table->unique(['template_id', 'version_no']);
		});

		Schema::create('gst_contracts', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('offer_id');
			$table->unsignedBigInteger('client_id');
			$table->unsignedBigInteger('template_id');
			$table->unsignedBigInteger('current_version_id')->nullable();
			$table->string('title', 190);
			$table->unsignedBigInteger('author_id')->nullable();
			$table->unsignedBigInteger('last_editor_id')->nullable();
			$table->unsignedBigInteger('deleted_by')->nullable();
			$table->timestamps();
			$table->softDeletes();
			$table->index('offer_id');
			$table->index('client_id');
			$table->index('template_id');
			$table->index('author_id');
		});

		Schema::create('gst_contract_versions', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('contract_id');
			$table->unsignedInteger('version_no');
			$table->string('status', 20)->default('draft');
			$table->unsignedBigInteger('template_version_id');
			$table->unsignedBigInteger('offer_version_id');
			$table->longText('body');
			$table->longText('data_snapshot');
			$table->char('content_hash', 64)->nullable();
			$table->unsignedBigInteger('created_by')->nullable();
			$table->unsignedBigInteger('approved_by')->nullable();
			$table->timestamp('approved_at')->nullable();
			$table->timestamps();
			$table->unique(['contract_id', 'version_no']);
			$table->index('template_version_id');
			$table->index('offer_version_id');
		});

		// Bez kluczy obcych i kaskad: wpisy dziennika zostają także po usunięciu tego, czego dotyczą.
		Schema::create('gst_contract_events', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('contract_id')->nullable();
			$table->unsignedBigInteger('template_id')->nullable();
			$table->unsignedInteger('version_no')->nullable();
			$table->unsignedBigInteger('user_id')->nullable();
			$table->string('type', 40);
			$table->text('payload')->nullable();
			$table->timestamp('created_at')->nullable();
			$table->index(['contract_id', 'id']);
			$table->index(['template_id', 'id']);
		});
	}

	public function down(): void
	{
		foreach (['gst_contract_events', 'gst_contract_versions', 'gst_contracts', 'gst_contract_template_versions', 'gst_contract_templates'] as $table) {
			Schema::dropIfExists($table);
		}
	}
};
