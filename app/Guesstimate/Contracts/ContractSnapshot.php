<?php

namespace App\Guesstimate\Contracts;

use App\Guesstimate\Offers\Offer;
use App\Guesstimate\Offers\OfferVersion;
use App\Guesstimate\Pricing\RiskLevel;
use App\Guesstimate\Settings\SettingsRepository;
use App\Guesstimate\Support\Format;

/**
 * Dane oferty i klienta zamrożone w wersji umowy. Widełki to nie kwota kontraktowa (decyzja 4), dlatego zapisany
 * jest cały wynik kalkulacji i wybrany wariant, a snapshot niczego nie rozstrzyga ani nie przelicza.
 * `values` to wartości wpisane ręcznie w umowie; dane źródłowe obok nich zostają nietknięte.
 * `payments` to uzgodniona kwota i harmonogram, wyłącznie z ręcznego wpisu (PaymentSchedule).
 */
final class ContractSnapshot
{
	public const SCHEMA = 3;

	public const CLIENT_FIELDS = ['company_name', 'nip', 'contact_name', 'email', 'phone', 'website_url'];

	public const CONTRACTOR_FIELDS = ['company_name', 'address', 'nip', 'representative'];

	public static function of(Offer $offer, OfferVersion $version, array $values = [], array $payments = []): array
	{
		$variant = $version->selected_variant;

		return self::compose(
			array_intersect_key($offer->client?->getAttributes() ?? [], array_flip(self::CLIENT_FIELDS)) + ['id' => $offer->client_id],
			[
				'id' => $offer->id,
				'number' => $offer->number,
				'title' => $version->project_name,
				'version_id' => $version->id,
				'version_no' => $version->version_no,
				'project_type' => $version->projectTypeLabel(),
				'currency' => $version->currency,
				'date' => Format::date($version->created_at),
				'description' => $version->description,
				'ux_scope' => $version->ux_label,
				'pages' => $version->nodes->sortBy('position')->pluck('name')->values()->all(),
				'features' => $version->features->pluck('name')->values()->all(),
				'variant' => RiskLevel::tryFrom((string) $variant)?->label(),
			],
			[
				'calc_model' => $version->calc_model,
				'selected_variant' => $variant,
				'amounts' => [
					'variant' => ['min' => $version->{"{$variant}_min"} ?? null, 'max' => $version->{"{$variant}_max"} ?? null],
					'range' => ['min' => $version->low_min, 'max' => $version->high_max],
				],
				'result' => $version->result,
			],
			now()->toIso8601String(),
			array_intersect_key((array) app(SettingsRepository::class)->get('contractor'), array_flip(self::CONTRACTOR_FIELDS)),
			$values,
			$payments,
		);
	}

	public static function compose(array $client, array $offer, array $pricing, string $takenAt, array $contractor = [], array $values = [], array $payments = []): array
	{
		return ['schema' => self::SCHEMA, 'taken_at' => $takenAt, 'client' => $client, 'offer' => $offer, 'pricing' => $pricing, 'contractor' => $contractor, 'values' => $values, 'payments' => $payments];
	}
}
