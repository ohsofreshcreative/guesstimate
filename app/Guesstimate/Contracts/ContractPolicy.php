<?php

namespace App\Guesstimate\Contracts;

use App\Guesstimate\Auth\Capabilities;
use App\Guesstimate\Offers\Offer;
use App\Guesstimate\Offers\OfferPolicy;
use Illuminate\Database\Eloquent\Builder;

/**
 * Uprawnienia do umów. Widoczność i edycja jak przy ofertach: pracownik własne, manager i administrator wszystkie.
 * Zatwierdzanie i szablony: osobne capabilities (propozycja z decyzji 8).
 */
class ContractPolicy
{
	public function __construct(private OfferPolicy $offers)
	{
	}

	public function canView(Contract $contract): bool
	{
		$me = get_current_user_id();

		return $this->offers->viewsAll() || $contract->author_id === $me || $contract->offer?->author_id === $me;
	}

	public function canCreate(Offer $offer): bool
	{
		return $this->offers->canEdit($offer);
	}

	public function canEdit(Contract $contract): bool
	{
		return current_user_can(Capabilities::CREATE_OFFERS)
			&& (current_user_can(Capabilities::EDIT_ALL_OFFERS) || $contract->author_id === get_current_user_id());
	}

	public function canApprove(): bool
	{
		return current_user_can(Capabilities::APPROVE_CONTRACTS);
	}

	public function canManageTemplates(): bool
	{
		return current_user_can(Capabilities::MANAGE_CONTRACT_TEMPLATES);
	}

	/** Ogranicza zapytanie o umowy do widocznych dla użytkownika. */
	public function scope(Builder $query): Builder
	{
		if (! $this->offers->viewsAll()) {
			$me = get_current_user_id();
			$query->where(fn (Builder $inner) => $inner->where('author_id', $me)->orWhereIn('offer_id', Offer::withTrashed()->where('author_id', $me)->select('id')));
		}

		return $query;
	}
}
