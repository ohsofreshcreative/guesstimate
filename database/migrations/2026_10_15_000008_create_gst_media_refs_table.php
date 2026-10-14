<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Edytor wizualny dokumentu: referencje opublikowanych ofert do załączników biblioteki mediów
 * (ochrona przed usunięciem plików używanych w publikacjach). Tylko dodaje tabelę i uzupełnia
 * referencje do teł z istniejących publikacji — snapshoty nie są zmieniane.
 */
return new class extends Migration
{
	public function up(): void
	{
		Schema::create('gst_media_refs', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('attachment_id');
			$table->unsignedBigInteger('offer_id');
			$table->unsignedBigInteger('publication_id');
			$table->timestamp('created_at')->nullable();
			$table->index('attachment_id');
			$table->index('offer_id');
			$table->index('publication_id');
		});

		// Starsze snapshoty zapisują tło jako wpis biblioteki teł (background_id) — wskazujemy jego załącznik.
		$backgrounds = DB::table('gst_backgrounds')->pluck('attachment_id', 'id');
		$now = now();

		DB::table('gst_publications')->select(['id', 'offer_id', 'snapshot'])->orderBy('id')->chunk(100, function ($rows) use ($backgrounds, $now) {
			$insert = [];

			foreach ($rows as $row) {
				$snapshot = json_decode((string) $row->snapshot, true) ?: [];
				$ids = [];

				foreach ((array) ($snapshot['document']['pages'] ?? []) as $page) {
					if (! empty($page['background_id']) && isset($backgrounds[$page['background_id']])) {
						$ids[] = (int) $backgrounds[$page['background_id']];
					}
				}

				foreach (array_unique($ids) as $id) {
					$insert[] = ['attachment_id' => $id, 'offer_id' => $row->offer_id, 'publication_id' => $row->id, 'created_at' => $now];
				}
			}

			if ($insert) {
				DB::table('gst_media_refs')->insert($insert);
			}
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('gst_media_refs');
	}
};
