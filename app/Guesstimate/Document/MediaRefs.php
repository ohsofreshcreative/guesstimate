<?php

namespace App\Guesstimate\Document;

use App\Guesstimate\Media\MediaUsage;
use App\Guesstimate\Offers\Publication;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;
use WP_Post;

/**
 * Referencje opublikowanych ofert do załączników biblioteki mediów (zdjęcia stron, tła, realizacje, logo).
 *
 * Snapshot publikacji zapisuje adresy plików, więc zmiana tytułu, opisu czy przycięcie obrazu w WordPressie
 * (nowy plik) nie zmienia opublikowanej oferty. Jedyne ryzyko to usunięcie załącznika — WordPress kasuje
 * wtedy pliki. Dlatego używanego załącznika nie da się usunąć (filtr pre_delete_attachment, użycia liczy
 * MediaUsage); pliki nie są kopiowane. Referencje znikają razem z publikacjami (trwałe usunięcie oferty).
 */
final class MediaRefs
{
	public const TABLE = 'gst_media_refs';

	/**
	 * Identyfikatory załączników ze snapshotu publikacji.
	 *
	 * @return list<int>
	 */
	public static function fromSnapshot(array $snapshot): array
	{
		$doc = (array) ($snapshot['document'] ?? []);
		$ids = [(int) ($doc['logo_id'] ?? 0)];

		foreach ((array) ($doc['pages'] ?? []) as $page) {
			$ids[] = (int) ($page['image']['id'] ?? 0);
			$ids[] = (int) ($page['background']['id'] ?? 0);
		}

		foreach ((array) ($doc['references']['items'] ?? []) as $item) {
			$ids[] = (int) ($item['image']['id'] ?? 0);
		}

		return array_values(array_unique(array_filter($ids)));
	}

	public static function record(Publication $publication): void
	{
		$now = now();
		$rows = array_map(fn ($id) => [
			'attachment_id' => $id,
			'offer_id' => $publication->offer_id,
			'publication_id' => $publication->id,
			'created_at' => $now,
		], self::fromSnapshot((array) $publication->snapshot));

		if ($rows) {
			DB::table(self::TABLE)->insert($rows);
		}
	}

	/**
	 * Numery ofert, których publikacje używają załącznika.
	 *
	 * @return list<string>
	 */
	public static function offersUsing(int $attachmentId): array
	{
		return DB::table(self::TABLE)
			->join('gst_offers', 'gst_offers.id', '=', self::TABLE . '.offer_id')
			->where(self::TABLE . '.attachment_id', $attachmentId)
			->distinct()
			->orderBy('gst_offers.number')
			->pluck('gst_offers.number')
			->all();
	}

	/**
	 * Filtr pre_delete_attachment: null = WordPress usuwa normalnie, false = usunięcie zablokowane.
	 */
	public static function guardDelete($delete, $post)
	{
		if ($delete !== null || ! $post instanceof WP_Post) {
			return $delete;
		}

		try {
			return MediaUsage::inUse(MediaUsage::forAttachment($post->ID)) ? false : $delete;
		} catch (Throwable) {
			// Bez tabel (migracja nie wykonana) nie blokujemy biblioteki mediów; przy innym błędzie plik zostaje.
			return self::installed() ? false : $delete;
		}
	}

	private static function installed(): bool
	{
		try {
			return Schema::hasTable(self::TABLE);
		} catch (Throwable) {
			return false;
		}
	}

	/**
	 * Informacja w szczegółach załącznika w bibliotece mediów.
	 */
	public static function describe(array $fields, $post): array
	{
		try {
			$summary = $post instanceof WP_Post ? MediaUsage::summary(MediaUsage::forAttachment($post->ID)) : '';
		} catch (Throwable) {
			$summary = '';
		}

		if ($summary !== '') {
			$fields['gst_media_refs'] = [
				'label' => 'Guesstimate',
				'input' => 'html',
				'html' => esc_html(sprintf('Tego pliku nie można usunąć — %s.', $summary)),
			];
		}

		return $fields;
	}
}
