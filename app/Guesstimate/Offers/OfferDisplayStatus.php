<?php

namespace App\Guesstimate\Offers;

use Illuminate\Database\Query\Builder;

/**
 * Status oferty prezentowany w tabelach ofert — jedno pole łączące status oferty (OfferStatus, decyzje klienta)
 * i stan publikacji online (PublicationStatus). Tylko prezentacja i filtrowanie: niczego nie zapisuje w bazie.
 *
 * Priorytet (pierwszy pasujący):
 * 1. Anulowana       — status oferty „anulowana”,
 * 2. Zaakceptowana   — decyzja klienta (ważniejsza niż sam fakt publikacji),
 * 3. Odrzucona       — decyzja klienta,
 * 4. Wycofana        — publikacja online wycofana,
 * 5. Opublikowana    — aktywna publikacja (także gdy wersja robocza ma nowe, nieopublikowane zmiany),
 * 6. Wysłana / Przygotowana / Robocza — status oferty bez publikacji.
 */
enum OfferDisplayStatus: string
{
	case Draft = 'draft';
	case Prepared = 'prepared';
	case Sent = 'sent';
	case Published = 'published';
	case Withdrawn = 'withdrawn';
	case Accepted = 'accepted';
	case Rejected = 'rejected';
	case Cancelled = 'cancelled';

	/** Statusy oferty, które mają pierwszeństwo przed stanem publikacji. */
	private const DECISIVE = ['cancelled', 'accepted', 'rejected'];

	public static function resolve(OfferStatus $status, PublicationStatus $publication): self
	{
		return match (true) {
			$status === OfferStatus::Cancelled => self::Cancelled,
			$status === OfferStatus::Accepted => self::Accepted,
			$status === OfferStatus::Rejected => self::Rejected,
			$publication === PublicationStatus::Withdrawn => self::Withdrawn,
			$publication === PublicationStatus::Published => self::Published,
			default => self::from($status->value), // draft | prepared | sent
		};
	}

	public function label(): string
	{
		return match ($this) {
			self::Draft => 'Robocza',
			self::Prepared => 'Przygotowana',
			self::Sent => 'Wysłana',
			self::Published => 'Opublikowana',
			self::Withdrawn => 'Wycofana',
			self::Accepted => 'Zaakceptowana',
			self::Rejected => 'Odrzucona',
			self::Cancelled => 'Anulowana',
		};
	}

	/**
	 * Opcje filtra w kolejności cyklu oferty.
	 *
	 * @return array<string, string>
	 */
	public static function options(): array
	{
		$options = [];

		foreach ([self::Draft, self::Prepared, self::Published, self::Sent, self::Withdrawn, self::Accepted, self::Rejected, self::Cancelled] as $case) {
			$options[$case->value] = $case->label();
		}

		return $options;
	}

	/**
	 * Warunek SQL zgodny z resolve() — filtr pokazuje dokładnie to, co widać w kolumnie „Status”.
	 * Kolumny: o.status, o.publication_status.
	 */
	public function apply(Builder $query): Builder
	{
		$open = fn (Builder $q) => $q->whereNotIn('o.status', self::DECISIVE);
		$unpublished = fn (Builder $q) => $q->where(fn ($w) => $w->whereNull('o.publication_status')
			->orWhereNotIn('o.publication_status', [PublicationStatus::Published->value, PublicationStatus::Withdrawn->value]));

		return match ($this) {
			self::Cancelled, self::Accepted, self::Rejected => $query->where('o.status', $this->value),
			self::Withdrawn => $open($query)->where('o.publication_status', PublicationStatus::Withdrawn->value),
			self::Published => $open($query)->where('o.publication_status', PublicationStatus::Published->value),
			self::Draft, self::Prepared, self::Sent => $unpublished($query->where('o.status', $this->value)),
		};
	}
}
