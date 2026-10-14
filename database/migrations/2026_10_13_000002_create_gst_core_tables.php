<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabele aplikacji Guesstimate. Kwoty w groszach (BIGINT), godziny DECIMAL(10,2).
 * Wersja oferty i jej tabele `gst_v_*` są historycznym snapshotem — nie zależą od bibliotek globalnych.
 */
return new class extends Migration
{
	public function up(): void
	{
		Schema::create('gst_page_types', function (Blueprint $table) {
			$table->id();
			$table->string('name', 150);
			$table->string('kind', 20)->default('template'); // unique | template
			$table->decimal('ui_min', 10, 2)->default(0);
			$table->decimal('ui_max', 10, 2)->default(0);
			$table->decimal('dev_min', 10, 2)->default(0);
			$table->decimal('dev_max', 10, 2)->default(0);
			$table->decimal('content_min', 10, 2)->default(0);
			$table->decimal('content_max', 10, 2)->default(0);
			$table->boolean('is_active')->default(true);
			$table->unsignedInteger('sort')->default(0);
			$table->timestamps();
		});

		Schema::create('gst_feature_categories', function (Blueprint $table) {
			$table->id();
			$table->string('name', 150);
			$table->unsignedInteger('sort')->default(0);
			$table->timestamps();
		});

		Schema::create('gst_features', function (Blueprint $table) {
			$table->id();
			$table->foreignId('category_id')->nullable();
			$table->string('name', 190);
			$table->text('description')->nullable();
			$table->decimal('hours_min', 10, 2)->default(0);
			$table->decimal('hours_max', 10, 2)->default(0);
			$table->string('stage_key', 30)->default('dev');
			$table->boolean('is_active')->default(true);
			$table->unsignedInteger('sort')->default(0);
			$table->unsignedBigInteger('created_by')->nullable();
			$table->timestamps();
			$table->index('category_id');
		});

		Schema::create('gst_clients', function (Blueprint $table) {
			$table->id();
			$table->string('company_name', 190);
			$table->string('website_url', 255)->nullable();
			$table->string('contact_name', 190)->nullable();
			$table->string('email', 190)->nullable();
			$table->string('phone', 50)->nullable();
			$table->string('nip', 20)->nullable();
			$table->text('notes')->nullable();
			$table->unsignedBigInteger('created_by')->nullable();
			$table->timestamps();
			$table->softDeletes();
			$table->index('company_name');
		});

		Schema::create('gst_number_sequences', function (Blueprint $table) {
			$table->id();
			$table->string('prefix', 20);
			$table->unsignedSmallInteger('year');
			$table->unsignedInteger('last_value')->default(0);
			$table->unique(['prefix', 'year']);
		});

		Schema::create('gst_offers', function (Blueprint $table) {
			$table->id();
			$table->string('number', 50)->unique();
			$table->foreignId('client_id')->nullable();
			$table->string('status', 20)->default('draft');
			$table->unsignedBigInteger('current_version_id')->nullable();
			$table->unsignedBigInteger('author_id');
			$table->unsignedBigInteger('last_editor_id')->nullable();
			$table->unsignedBigInteger('duplicated_from_id')->nullable();
			$table->timestamps();
			$table->softDeletes();
			$table->index(['status', 'created_at']);
			$table->index('author_id');
			$table->index('client_id');
		});

		Schema::create('gst_offer_versions', function (Blueprint $table) {
			$table->id();
			$table->foreignId('offer_id');
			$table->unsignedInteger('version_no');
			$table->timestamp('locked_at')->nullable();
			$table->string('project_name', 190);
			$table->string('current_url', 255)->nullable();
			$table->json('project_types');
			$table->text('description')->nullable();
			$table->text('internal_notes')->nullable();
			$table->string('calc_model', 20)->default('hourly');
			$table->string('ux_scope', 20);
			$table->string('ux_label', 100);
			$table->decimal('ux_hours_min', 10, 2);
			$table->decimal('ux_hours_max', 10, 2);
			$table->unsignedBigInteger('rate_low');
			$table->unsignedBigInteger('rate_mid');
			$table->unsignedBigInteger('rate_high');
			$table->boolean('rates_overridden')->default(false);
			$table->string('currency', 3)->default('PLN');
			$table->json('settings_snapshot');
			$table->json('result')->nullable();
			$table->string('result_error', 255)->nullable();
			$table->decimal('hours_min', 10, 2)->nullable();
			$table->decimal('hours_max', 10, 2)->nullable();
			$table->unsignedBigInteger('low_min')->nullable();
			$table->unsignedBigInteger('low_max')->nullable();
			$table->unsignedBigInteger('mid_min')->nullable();
			$table->unsignedBigInteger('mid_max')->nullable();
			$table->unsignedBigInteger('high_min')->nullable();
			$table->unsignedBigInteger('high_max')->nullable();
			$table->unsignedBigInteger('recurring_monthly')->default(0);
			$table->unsignedBigInteger('recurring_yearly')->default(0);
			$table->string('engine_version', 20)->nullable();
			$table->unsignedTinyInteger('wizard_step')->default(1);
			$table->string('change_note', 255)->nullable();
			$table->unsignedBigInteger('created_by');
			$table->unsignedBigInteger('updated_by')->nullable();
			$table->timestamps();
			$table->unique(['offer_id', 'version_no']);
		});

		Schema::create('gst_v_stages', function (Blueprint $table) {
			$table->id();
			$table->foreignId('version_id');
			$table->string('stage_key', 30);
			$table->string('name', 100);
			$table->boolean('enabled')->default(true);
			$table->boolean('in_pool')->default(false);
			$table->decimal('percent', 7, 4)->default(0);
			$table->unsignedBigInteger('fixed_amount')->nullable();
			$table->decimal('manual_min', 10, 2)->default(0);
			$table->decimal('manual_max', 10, 2)->default(0);
			$table->unsignedInteger('sort')->default(0);
			$table->unique(['version_id', 'stage_key']);
		});

		Schema::create('gst_v_templates', function (Blueprint $table) {
			$table->id();
			$table->foreignId('version_id');
			$table->unsignedBigInteger('page_type_id')->nullable();
			$table->string('page_type_name', 150);
			$table->string('kind', 20);
			$table->string('name', 190);
			$table->decimal('ui_min', 10, 2)->default(0);
			$table->decimal('ui_max', 10, 2)->default(0);
			$table->decimal('dev_min', 10, 2)->default(0);
			$table->decimal('dev_max', 10, 2)->default(0);
			$table->decimal('content_min', 10, 2)->default(0);
			$table->decimal('content_max', 10, 2)->default(0);
			$table->string('client_key', 40)->nullable();
			$table->index('version_id');
		});

		Schema::create('gst_v_sitemap_nodes', function (Blueprint $table) {
			$table->id();
			$table->foreignId('version_id');
			$table->unsignedBigInteger('parent_id')->nullable();
			$table->unsignedInteger('position')->default(0);
			$table->string('name', 190);
			$table->unsignedBigInteger('template_id')->nullable();
			$table->unsignedInteger('instances')->default(1);
			$table->boolean('content_entry')->default(false);
			$table->decimal('content_min', 10, 2)->default(0);
			$table->decimal('content_max', 10, 2)->default(0);
			$table->decimal('extra_min', 10, 2)->default(0);
			$table->decimal('extra_max', 10, 2)->default(0);
			$table->text('notes')->nullable();
			$table->index(['version_id', 'parent_id', 'position']);
		});

		Schema::create('gst_v_features', function (Blueprint $table) {
			$table->id();
			$table->foreignId('version_id');
			$table->unsignedBigInteger('feature_id')->nullable();
			$table->string('category_name', 150)->nullable();
			$table->string('name', 190);
			$table->text('description')->nullable();
			$table->text('notes')->nullable();
			$table->decimal('hours_min', 10, 2)->default(0);
			$table->decimal('hours_max', 10, 2)->default(0);
			$table->string('stage_key', 30)->default('dev');
			$table->boolean('is_custom')->default(false);
			$table->unsignedInteger('sort')->default(0);
			$table->index('version_id');
		});

		Schema::create('gst_v_work_items', function (Blueprint $table) {
			$table->id();
			$table->foreignId('version_id');
			$table->string('name', 190);
			$table->string('stage_key', 30)->default('other');
			$table->decimal('hours_min', 10, 2)->default(0);
			$table->decimal('hours_max', 10, 2)->default(0);
			$table->text('notes')->nullable();
			$table->unsignedInteger('sort')->default(0);
			$table->index('version_id');
		});

		Schema::create('gst_v_costs', function (Blueprint $table) {
			$table->id();
			$table->foreignId('version_id');
			$table->string('name', 190);
			$table->string('type', 30)->default('other'); // license | plugin | service | hosting | other
			$table->unsignedBigInteger('amount');
			$table->decimal('quantity', 10, 2)->default(1);
			$table->string('billing', 20)->default('one_time'); // one_time | monthly | yearly
			$table->text('notes')->nullable();
			$table->unsignedInteger('sort')->default(0);
			$table->index('version_id');
		});

		Schema::create('gst_offer_events', function (Blueprint $table) {
			$table->id();
			$table->foreignId('offer_id');
			$table->unsignedBigInteger('version_id')->nullable();
			$table->unsignedBigInteger('user_id')->nullable();
			$table->string('type', 40);
			$table->json('payload')->nullable();
			$table->timestamp('created_at')->nullable();
			$table->index(['offer_id', 'created_at']);
		});
	}

	public function down(): void
	{
		foreach ([
			'gst_offer_events', 'gst_v_costs', 'gst_v_work_items', 'gst_v_features', 'gst_v_sitemap_nodes',
			'gst_v_templates', 'gst_v_stages', 'gst_offer_versions', 'gst_offers', 'gst_number_sequences',
			'gst_clients', 'gst_features', 'gst_feature_categories', 'gst_page_types',
		] as $table) {
			Schema::dropIfExists($table);
		}
	}
};
