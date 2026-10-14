<?php

namespace App\Guesstimate\Dashboard;

use App\Guesstimate\Offers\OfferPolicy;
use App\Guesstimate\Offers\OfferStatus;
use Illuminate\Support\Facades\DB;

/**
 * Statystyki pulpitu liczone z ofert widocznych dla użytkownika.
 *
 * - łączna wartość: suma widełek Mid Risk (od–do) bieżących wersji, bez ofert anulowanych,
 * - skuteczność: zaakceptowane / (zaakceptowane + odrzucone).
 */
class DashboardStats
{
	public function __construct(private OfferPolicy $policy)
	{
	}

	public function compute(): array
	{
		$query = $this->policy->scope(DB::table('gst_offers as o')->whereNull('o.deleted_at'));

		$counts = [];

		foreach (OfferStatus::cases() as $status) {
			$counts[$status->value] = (clone $query)->where('o.status', $status->value)->count();
		}

		$valueQuery = (clone $query)
			->join('gst_offer_versions as v', 'v.id', '=', 'o.current_version_id')
			->where('o.status', '!=', OfferStatus::Cancelled->value);

		$value = (object) [
			'value_min' => (clone $valueQuery)->sum('v.mid_min'),
			'value_max' => (clone $valueQuery)->sum('v.mid_max'),
		];

		$accepted = $counts[OfferStatus::Accepted->value] ?? 0;
		$rejected = $counts[OfferStatus::Rejected->value] ?? 0;
		$decided = $accepted + $rejected;

		return [
			'counts' => $counts,
			'total' => array_sum($counts),
			'value_min' => (int) $value->value_min,
			'value_max' => (int) $value->value_max,
			'win_rate' => $decided > 0 ? round($accepted / $decided * 100, 1) : null,
			'decided' => $decided,
		];
	}
}
