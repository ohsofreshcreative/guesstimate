<?php

namespace App\Guesstimate\Contracts;

/**
 * Status wersji umowy i wersji szablonu. Kolumna tekstowa: kolejne statusy (decyzja 3) nie wymagają zmiany schematu.
 */
enum ContractStatus: string
{
	case Draft = 'draft';
	case Approved = 'approved';

	public function label(): string
	{
		return match ($this) {
			self::Draft => 'Szkic',
			self::Approved => 'Zatwierdzona',
		};
	}

	public function isEditable(): bool
	{
		return $this === self::Draft;
	}
}
