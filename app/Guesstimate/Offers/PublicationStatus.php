<?php

namespace App\Guesstimate\Offers;

/**
 * Status publikacji oferty online — niezależny od statusu handlowego.
 */
enum PublicationStatus: string
{
	case Draft = 'draft';
	case Published = 'published';
	case Withdrawn = 'withdrawn';

	public function label(): string
	{
		return match ($this) {
			self::Draft => 'Szkic',
			self::Published => 'Opublikowana',
			self::Withdrawn => 'Wycofana',
		};
	}

	public function tone(): string
	{
		return match ($this) {
			self::Draft => 'draft',
			self::Published => 'accepted',
			self::Withdrawn => 'cancelled',
		};
	}
}
