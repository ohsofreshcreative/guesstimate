<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Oferta jako dokument (strony 16:9): biblioteka teł (załączniki WordPressa), realizacje
 * i ustawienia dokumentu w wersji oferty. Tylko dodaje tabele i kolumnę.
 */
return new class extends Migration
{
	public function up(): void
	{
		Schema::create('gst_backgrounds', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('attachment_id');
			$table->string('name', 150);
			$table->string('fit', 10)->default('cover');
			$table->unsignedInteger('width')->nullable();
			$table->unsignedInteger('height')->nullable();
			$table->unsignedInteger('sort')->default(0);
			$table->timestamps();
			$table->index('attachment_id');
		});

		Schema::create('gst_case_studies', function (Blueprint $table) {
			$table->id();
			$table->string('client_name', 150);
			$table->unsignedBigInteger('attachment_id')->nullable();
			$table->string('url', 255)->nullable();
			$table->text('description')->nullable();
			$table->json('metrics')->nullable();
			$table->boolean('is_active')->default(true);
			$table->unsignedInteger('sort')->default(0);
			$table->timestamps();
		});

		Schema::table('gst_offer_versions', function (Blueprint $table) {
			$table->json('document')->nullable()->after('content');
		});

		// Realizacje z oferty referencyjnej (FARMAK × OhSoFresh) — bez zdjęć; administrator dodaje je w ustawieniach.
		$now = now();
		$seed = [
			['indahome', 'https://www.indahome.pl', [['56%+', 'Wzrost współczynnika konwersji'], ['28', 'Unikalnych ekranów strony www']]],
			['Britannia', 'https://www.britannia.edu.pl', [['370%+', 'Wzrost współczynnika konwersji'], ['5 tys.', 'Wyświetleń w pierwszym msc.']]],
			['Interdecor Pro', 'https://www.interdecorpro.pl', [['400%+', 'Wzrost liczby wejść na stronę na msc.'], ['10×', 'Wzrost zaangażowania użytkowników']]],
		];

		if (DB::table('gst_case_studies')->count() === 0) {
			foreach ($seed as $sort => [$name, $url, $metrics]) {
				DB::table('gst_case_studies')->insert([
					'client_name' => $name,
					'url' => $url,
					'metrics' => json_encode(array_map(fn ($m) => ['value' => $m[0], 'label' => $m[1]], $metrics), JSON_UNESCAPED_UNICODE),
					'is_active' => true,
					'sort' => $sort,
					'created_at' => $now,
					'updated_at' => $now,
				]);
			}
		}
	}

	public function down(): void
	{
		Schema::table('gst_offer_versions', function (Blueprint $table) {
			$table->dropColumn('document');
		});
		Schema::dropIfExists('gst_case_studies');
		Schema::dropIfExists('gst_backgrounds');
	}
};
