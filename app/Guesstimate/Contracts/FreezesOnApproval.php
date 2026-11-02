<?php

namespace App\Guesstimate\Contracts;

use LogicException;

/**
 * Zatwierdzony rekord jest niezmienny: zmiana treści to nowa wersja. Blokada na poziomie modelu, więc obowiązuje
 * każdą ścieżkę przez Eloquent, także przyszłe kontrolery. Bez tego historia dałaby się po cichu nadpisać.
 */
trait FreezesOnApproval
{
	protected static function bootFreezesOnApproval(): void
	{
		static::updating(fn ($model) => $model->assertNotFrozen());
		static::deleting(fn ($model) => $model->assertNotFrozen());
	}

	public function assertNotFrozen(): void
	{
		if ($this->getOriginal('status') === ContractStatus::Approved) {
			throw new LogicException('Zatwierdzonej wersji nie można zmienić ani usunąć. Utwórz nową wersję.');
		}
	}

	public function isApproved(): bool
	{
		return $this->status === ContractStatus::Approved;
	}

	/** Treść i dane odpowiadają temu, co zatwierdzono. */
	public function isIntact(): bool
	{
		return $this->content_hash !== null && hash_equals($this->content_hash, $this->fingerprint());
	}
}
