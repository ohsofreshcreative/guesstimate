<?php

namespace App\Guesstimate\Offers;

use App\Guesstimate\Document\DocumentSettings;
use App\Guesstimate\Document\MediaRefs;
use App\Guesstimate\Http\Controllers\OfferController;
use App\Guesstimate\Settings\SettingsRepository;
use App\Guesstimate\Support\Format;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Publikacja oferty online (/offer/{token}/). Status publikacji jest niezależny od statusu handlowego.
 * Publiczna strona pokazuje snapshot — nowa wersja publiczna powstaje tylko po świadomym „Opublikuj nową wersję”.
 */
class PublicationService
{
	public function __construct(private OfferService $offers)
	{
	}

	/**
	 * Braki blokujące publikację bieżącej wersji.
	 *
	 * @return list<string>
	 */
	public function blockers(Offer $offer): array
	{
		$version = $offer->currentVersion;
		$blockers = [];

		if (! $version->isBudget()) {
			$blockers[] = 'Ta wersja jest zapisana w poprzednim modelu kalkulacji. Utwórz nową wersję, aby ją opublikować.';
		}

		if ($version->result_error || ! $version->result) {
			$blockers[] = 'Popraw kalkulację: ' . ($version->result_error ?: 'brak wyniku.');
		}

		if ($offer->status === OfferStatus::Cancelled) {
			$blockers[] = 'Anulowanej oferty nie można publikować.';
		}

		if ($version->isBudget()) {
			$blockers = array_merge($blockers, OfferContent::missing($version->content));

			if (self::situationOverflows($version)) {
				$blockers[] = 'Strona „Jak rozumiemy Twoją sytuację?” nie mieści się na jednej stronie A4. Skróć tekst w edytorze dokumentu (np. „Dopasuj tekst do strony z AI”) albo zmień układ na pełną szerokość.';
			}
		}

		return $blockers;
	}

	/**
	 * Pomiar z edytora: tekst strony „Sytuacja” nie mieści się na A4 nawet po dopasowaniu typografii.
	 * Ważny tylko dla stanu, który był mierzony (skrót treści, nagłówka, układu i zdjęcia).
	 */
	public static function situationOverflows(OfferVersion $version): bool
	{
		$doc = DocumentSettings::forVersion($version, app(SettingsRepository::class)->all());
		$report = $doc['fit_report']['situation'] ?? null;

		return is_array($report) && ! empty($report['overflow'])
			&& ($report['hash'] ?? '') === DocumentSettings::fitHash($doc, OfferContent::normalize($version->content));
	}

	/**
	 * Czy klient widzi coś innego niż bieżąca wersja robocza (inna wersja albo zmiany po publikacji).
	 */
	public static function outdated(Offer $offer, ?Publication $publication): bool
	{
		if (! $publication) {
			return false;
		}

		$version = $offer->currentVersion;

		if ($publication->version_id !== $version->id) {
			return true;
		}

		if (! $version->isBudget() || ! $version->result) {
			return false;
		}

		return self::fingerprint((array) $publication->snapshot) !== self::fingerprint(PublicOffer::build($offer, $version));
	}

	/**
	 * Odcisk snapshotu bez daty okładki (zmienia się codziennie) i niezależny od kolejności kluczy JSON.
	 */
	public static function fingerprint(array $snapshot): string
	{
		unset($snapshot['document']['cover']['date']);

		$normalize = function ($value) use (&$normalize) {
			if (! is_array($value)) {
				return $value;
			}

			$value = array_map($normalize, $value);

			if (! array_is_list($value)) {
				ksort($value);
			}

			return $value;
		};

		return md5((string) wp_json_encode($normalize($snapshot)));
	}

	public function publish(Offer $offer): Publication
	{
		if ($blockers = $this->blockers($offer)) {
			throw new InvalidArgumentException(implode(' ', $blockers));
		}

		return DB::transaction(function () use ($offer) {
			$offer = Offer::whereKey($offer->id)->lockForUpdate()->first();
			$version = $offer->currentVersion;

			// Podwójne wysłanie (np. dwukrotne kliknięcie „Opublikuj”): drugie żądanie czeka na blokadę wiersza oferty
			// i widzi świeżą publikację tej samej wersji z identyczną treścią — nie tworzy kolejnej.
			if ($duplicate = $this->recentDuplicate($offer, $version)) {
				return $duplicate;
			}

			if (! $offer->public_token) {
				$offer->public_token = self::token();
			}

			if ($offer->publication_id) {
				Publication::whereKey($offer->publication_id)->whereNull('withdrawn_at')->update(['withdrawn_at' => now(), 'withdrawn_by' => get_current_user_id()]);
			}

			$publication = Publication::create([
				'offer_id' => $offer->id,
				'version_id' => $version->id,
				'version_no' => $version->version_no,
				'number' => (int) Publication::where('offer_id', $offer->id)->max('number') + 1,
				'snapshot' => PublicOffer::build($offer, $version),
				'published_by' => get_current_user_id(),
				'published_at' => now(),
			]);

			MediaRefs::record($publication);

			$offer->publication_status = PublicationStatus::Published;
			$offer->publication_id = $publication->id;
			$offer->save();

			$this->offers->log($offer, 'published', $version->id, ['number' => $publication->number, 'version_no' => $version->version_no]);

			return $publication;
		});
	}

	private const DUPLICATE_WINDOW = 30; // s

	private function recentDuplicate(Offer $offer, OfferVersion $version): ?Publication
	{
		if ($offer->publication_status !== PublicationStatus::Published || ! $offer->publication_id) {
			return null;
		}

		$active = Publication::whereKey($offer->publication_id)->whereNull('withdrawn_at')->first();

		if (! $active || (int) $active->version_id !== (int) $version->id || ! $active->published_at
			|| $active->published_at->lt(now()->subSeconds(self::DUPLICATE_WINDOW))
			|| wp_json_encode($active->snapshot) !== wp_json_encode(PublicOffer::build($offer, $version))) {
			return null;
		}

		$active->duplicate = true;

		return $active;
	}

	public function withdraw(Offer $offer): void
	{
		if ($offer->publication_status !== PublicationStatus::Published) {
			throw new InvalidArgumentException('Oferta nie jest opublikowana.');
		}

		DB::transaction(function () use ($offer) {
			Publication::whereKey($offer->publication_id)->update(['withdrawn_at' => now(), 'withdrawn_by' => get_current_user_id()]);
			$offer->update(['publication_status' => PublicationStatus::Withdrawn]);
			$this->offers->log($offer, 'withdrawn', $offer->current_version_id);
		});
	}

	/**
	 * Ponowna publikacja ostatniego snapshotu (bez zmian treści i cen) pod tym samym linkiem.
	 */
	public function restore(Offer $offer): void
	{
		if ($offer->publication_status !== PublicationStatus::Withdrawn || ! $offer->publication_id) {
			throw new InvalidArgumentException('Nie ma wycofanej publikacji do przywrócenia.');
		}

		DB::transaction(function () use ($offer) {
			$publication = Publication::findOrFail($offer->publication_id);
			$publication->update(['withdrawn_at' => null, 'withdrawn_by' => null]);
			$offer->update(['publication_status' => PublicationStatus::Published]);
			$this->offers->log($offer, 'republished', $offer->current_version_id, ['number' => $publication->number]);
		});
	}

	public static function url(Offer $offer): ?string
	{
		return $offer->public_token ? home_url('/offer/' . $offer->public_token . '/') : null;
	}

	/**
	 * Dane panelu publikacji (krok 6 i szczegóły oferty).
	 */
	public function panel(Offer $offer): array
	{
		$offer->loadMissing(['publication', 'currentVersion']);
		$publication = $offer->publication;
		$status = $offer->publication_status ?? PublicationStatus::Draft;

		return [
			'publication' => [
				'status' => $status,
				'url' => self::url($offer),
				'preview_url' => route('gst.offers.public-preview', $offer),
				'published_version' => $publication?->version_no,
				'published_at' => Format::date($publication?->published_at, true),
				'published_by' => $publication ? OfferController::userName($publication->published_by) : null,
				'outdated' => self::outdated($offer, $publication),
				'views' => self::views($offer),
				'history' => $offer->publications()->limit(20)->get()->map(fn (Publication $p) => [
					'number' => $p->number,
					'version_no' => $p->version_no,
					'published_at' => Format::date($p->published_at, true),
					'published_by' => OfferController::userName($p->published_by),
					'withdrawn_at' => Format::date($p->withdrawn_at, true),
					'active' => $p->id === $offer->publication_id && $status === PublicationStatus::Published,
				])->all(),
			],
			'blockers' => $this->blockers($offer),
		];
	}

	public static function views(Offer $offer): array
	{
		return [
			'count' => (int) $offer->view_count,
			'first' => $offer->first_viewed_at ? sprintf('%s o %s', wp_date('d.m.Y', $offer->first_viewed_at->getTimestamp()), wp_date('H:i', $offer->first_viewed_at->getTimestamp())) : null,
			'last' => $offer->last_viewed_at ? sprintf('%s o %s', wp_date('d.m.Y', $offer->last_viewed_at->getTimestamp()), wp_date('H:i', $offer->last_viewed_at->getTimestamp())) : null,
		];
	}

	private static function token(): string
	{
		do {
			$token = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
		} while (Offer::withTrashed()->where('public_token', $token)->exists());

		return $token;
	}
}
