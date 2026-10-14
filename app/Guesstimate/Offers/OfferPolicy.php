<?php

namespace App\Guesstimate\Offers;

use App\Guesstimate\Auth\Capabilities;
use Illuminate\Database\Query\Builder;

/**
 * Uprawnienia do ofert — jedno miejsce dla kontrolerów, zapytań i widoków.
 * Pracownik widzi i edytuje tylko własne oferty; manager i administrator — wszystkie.
 */
class OfferPolicy
{
	public function canCreate(): bool
	{
		return current_user_can(Capabilities::CREATE_OFFERS);
	}

	public function canView(Offer $offer): bool
	{
		return current_user_can(Capabilities::VIEW_ALL_OFFERS) || $offer->author_id === get_current_user_id();
	}

	public function canEdit(Offer $offer): bool
	{
		if (! current_user_can(Capabilities::CREATE_OFFERS)) {
			return false;
		}

		return current_user_can(Capabilities::EDIT_ALL_OFFERS) || $offer->author_id === get_current_user_id();
	}

	public function canDuplicate(Offer $offer): bool
	{
		return $this->canCreate() && $this->canView($offer);
	}

	/**
	 * Przeniesienie do kosza: jak edycja (pracownik — tylko własne oferty, manager i administrator — wszystkie).
	 */
	public function canTrash(Offer $offer): bool
	{
		return $this->canEdit($offer);
	}

	public function canManageTrash(): bool
	{
		return current_user_can(Capabilities::MANAGE_TRASH);
	}

	/**
	 * Przywrócenie: manager i administrator — każdą ofertę; pracownik — tylko własną, którą sam usunął
	 * (szybkie „Cofnij” po przeniesieniu do kosza).
	 */
	public function canRestore(Offer $offer): bool
	{
		if ($this->canManageTrash()) {
			return true;
		}

		return $this->canEdit($offer) && (int) $offer->deleted_by === get_current_user_id();
	}

	public function canForceDelete(): bool
	{
		return current_user_can(Capabilities::DELETE_OFFERS);
	}

	public function viewsAll(): bool
	{
		return current_user_can(Capabilities::VIEW_ALL_OFFERS);
	}

	/**
	 * Ogranicza zapytanie o oferty (alias tabeli `o`) do ofert widocznych dla użytkownika.
	 */
	public function scope(Builder $query, string $alias = 'o'): Builder
	{
		if (! $this->viewsAll()) {
			$query->where($alias . '.author_id', get_current_user_id());
		}

		return $query;
	}

	public function authorizeView(Offer $offer): void
	{
		abort_unless($this->canView($offer), 403, 'Ta oferta należy do innego pracownika.');
	}

	public function authorizeEdit(Offer $offer): void
	{
		abort_unless($this->canEdit($offer), 403, 'Nie możesz edytować tej oferty.');
	}
}
