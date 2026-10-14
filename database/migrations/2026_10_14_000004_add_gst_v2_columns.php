<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP v2: model budżetowy, wybrany wariant, treści oferty, publikacja online, wyświetlenia,
 * powiązanie klientów z ClickUp i dziennik wywołań AI.
 * Tylko dodaje kolumny i tabele — istniejące dane i wyceny pozostają bez zmian.
 */
return new class extends Migration
{
	public function up(): void
	{
		Schema::table('gst_offer_versions', function (Blueprint $table) {
			$table->string('project_type', 30)->nullable()->after('project_types');
			$table->json('budget')->nullable()->after('settings_snapshot');
			$table->string('selected_variant', 10)->default('mid')->after('budget');
			$table->json('content')->nullable()->after('selected_variant');
		});

		Schema::table('gst_v_sitemap_nodes', function (Blueprint $table) {
			$table->decimal('hours_ui_min', 10, 2)->nullable()->after('extra_max');
			$table->decimal('hours_ui_max', 10, 2)->nullable()->after('hours_ui_min');
			$table->decimal('hours_dev_min', 10, 2)->nullable()->after('hours_ui_max');
			$table->decimal('hours_dev_max', 10, 2)->nullable()->after('hours_dev_min');
		});

		Schema::table('gst_offers', function (Blueprint $table) {
			$table->string('publication_status', 20)->default('draft')->after('status');
			$table->string('public_token', 64)->nullable()->unique()->after('publication_status');
			$table->unsignedBigInteger('publication_id')->nullable()->after('public_token');
			$table->timestamp('first_viewed_at')->nullable();
			$table->timestamp('last_viewed_at')->nullable();
			$table->unsignedInteger('view_count')->default(0);
			$table->index('publication_status');
		});

		Schema::create('gst_publications', function (Blueprint $table) {
			$table->id();
			$table->foreignId('offer_id');
			$table->unsignedBigInteger('version_id');
			$table->unsignedInteger('version_no');
			$table->unsignedInteger('number');
			$table->json('snapshot');
			$table->unsignedBigInteger('published_by')->nullable();
			$table->timestamp('published_at')->nullable();
			$table->timestamp('withdrawn_at')->nullable();
			$table->unsignedBigInteger('withdrawn_by')->nullable();
			$table->index(['offer_id', 'number']);
		});

		Schema::create('gst_offer_views', function (Blueprint $table) {
			$table->id();
			$table->foreignId('offer_id');
			$table->unsignedBigInteger('publication_id');
			$table->char('visitor_hash', 64);
			$table->timestamp('viewed_at')->nullable();
			$table->index(['offer_id', 'viewed_at']);
			$table->index(['publication_id', 'visitor_hash']);
		});

		Schema::table('gst_clients', function (Blueprint $table) {
			$table->string('clickup_id', 40)->nullable()->unique()->after('notes');
			$table->timestamp('clickup_synced_at')->nullable()->after('clickup_id');
		});

		Schema::create('gst_ai_requests', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('user_id');
			$table->unsignedBigInteger('offer_id')->nullable();
			$table->string('kind', 30);
			$table->string('status', 20);
			$table->string('model', 80)->nullable();
			$table->unsignedInteger('tokens_in')->nullable();
			$table->unsignedInteger('tokens_out')->nullable();
			$table->string('error', 255)->nullable();
			$table->timestamp('created_at')->nullable();
			$table->index(['user_id', 'created_at']);
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('gst_ai_requests');
		Schema::dropIfExists('gst_offer_views');
		Schema::dropIfExists('gst_publications');

		Schema::table('gst_clients', function (Blueprint $table) {
			$table->dropUnique(['clickup_id']);
			$table->dropColumn(['clickup_id', 'clickup_synced_at']);
		});

		Schema::table('gst_offers', function (Blueprint $table) {
			$table->dropIndex(['publication_status']);
			$table->dropUnique(['public_token']);
			$table->dropColumn(['publication_status', 'public_token', 'publication_id', 'first_viewed_at', 'last_viewed_at', 'view_count']);
		});

		Schema::table('gst_v_sitemap_nodes', function (Blueprint $table) {
			$table->dropColumn(['hours_ui_min', 'hours_ui_max', 'hours_dev_min', 'hours_dev_max']);
		});

		Schema::table('gst_offer_versions', function (Blueprint $table) {
			$table->dropColumn(['project_type', 'budget', 'selected_variant', 'content']);
		});
	}
};
