<?php

namespace App\Guesstimate\Offers;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Kosz ofert (soft delete: deleted_at + deleted_by).
 *
 * - przeniesienie do kosza wyłącza publiczny link (publikacja zostaje wycofana, historia publikacji
 *   i wyświetleń zostaje); oferta znika z archiwum, pulpitu i wyszukiwarek,
 * - przywrócenie przywraca ofertę z numerem, klientem, wersjami i historią — ale bez ponownej publikacji,
 * - trwałe usunięcie (tylko z kosza) kasuje ofertę razem z jej wersjami, publikacjami, wyświetleniami
 *   i historią. Klient i pliki w bibliotece mediów zostają.
 */
class TrashService
{
	public const VERSION_TABLES = ['gst_v_stages', 'gst_v_templates', 'gst_v_sitemap_nodes', 'gst_v_features', 'gst_v_work_items', 'gst_v_costs'];

	public function __construct(private OfferService $offers)
	{
	}

	public function trash(Offer $offer): void
	{
		DB::transaction(function () use ($offer) {
			$offer = Offer::whereKey($offer->id)->lockForUpdate()->firstOrFail();
			$wasPublished = $offer->publication_status === PublicationStatus::Published;

			if ($wasPublished) {
				Publication::whereKey($offer->publication_id)->whereNull('withdrawn_at')->update(['withdrawn_at' => now(), 'withdrawn_by' => get_current_user_id()]);
				$offer->publication_status = PublicationStatus::Withdrawn;
			}

			$offer->deleted_by = get_current_user_id();
			$offer->save();
			$this->offers->log($offer, 'trashed', $offer->current_version_id, ['was_published' => $wasPublished]);
			$offer->delete();
		});
	}

	public function restore(Offer $offer): void
	{
		if (! $offer->trashed()) {
			throw new InvalidArgumentException('Ta oferta nie jest w koszu.');
		}

		DB::transaction(function () use ($offer) {
			$offer->restore();
			$offer->update(['deleted_by' => null]);
			$this->offers->log($offer, 'restored', $offer->current_version_id);
		});
	}

	/**
	 * @return array<string, int> liczba usuniętych rekordów (do testów i dziennika)
	 */
	public function forceDelete(Offer $offer): array
	{
		if (! $offer->trashed()) {
			throw new InvalidArgumentException('Na stałe można usunąć tylko ofertę z kosza.');
		}

		return DB::transaction(function () use ($offer) {
			$versionIds = DB::table('gst_offer_versions')->where('offer_id', $offer->id)->pluck('id')->all();
			$counts = [];

			foreach (self::VERSION_TABLES as $table) {
				$counts[$table] = $versionIds ? DB::table($table)->whereIn('version_id', $versionIds)->delete() : 0;
			}

			$counts['gst_offer_views'] = DB::table('gst_offer_views')->where('offer_id', $offer->id)->delete();
			$counts['gst_media_refs'] = DB::table('gst_media_refs')->where('offer_id', $offer->id)->delete();
			$counts['gst_publications'] = DB::table('gst_publications')->where('offer_id', $offer->id)->delete();
			$counts['gst_offer_events'] = DB::table('gst_offer_events')->where('offer_id', $offer->id)->delete();
			$counts['gst_offer_versions'] = DB::table('gst_offer_versions')->where('offer_id', $offer->id)->delete();

			// Dziennik AI i duplikaty innych ofert zostają, ale bez wskazania na usuniętą ofertę.
			DB::table('gst_ai_requests')->where('offer_id', $offer->id)->update(['offer_id' => null]);
			DB::table('gst_offers')->where('duplicated_from_id', $offer->id)->update(['duplicated_from_id' => null]);

			$counts['gst_offers'] = DB::table('gst_offers')->where('id', $offer->id)->delete();

			return $counts;
		});
	}
}
