<?php

namespace App\Guesstimate\Http\Controllers;

use App\Guesstimate\Dashboard\DashboardStats;
use App\Guesstimate\Offers\OfferPolicy;
use App\Guesstimate\Offers\OfferQuery;
use App\Guesstimate\Offers\OfferDisplayStatus;
use App\Guesstimate\Offers\OfferStatus;
use App\Guesstimate\Offers\OfferSummary;
use App\Guesstimate\Offers\OfferVersion;
use App\Guesstimate\Support\Format;
use App\Guesstimate\Support\Modules;
use Illuminate\Http\Request;

class DashboardController
{
	public const RECENT = 10;

	public function __construct(private DashboardStats $stats, private OfferQuery $query, private OfferPolicy $policy)
	{
	}

	public function __invoke(Request $request)
	{
		$stats = $this->stats->compute();
		$result = $this->query->search($request->query(), self::RECENT);

		return view('guesstimate.dashboard', [
			'module' => Modules::find('dashboard'),
			'pipeline' => array_map(fn (OfferStatus $status) => [
				'label' => $status->label(),
				'value' => $stats['counts'][$status->value] ?? 0,
				'tone' => $status->value,
				'url' => route('gst.offers.index', ['etap' => $status->value]),
			], OfferStatus::cases()),
			'totalOffers' => $stats['total'],
			'totalValue' => Format::moneyRange($stats['value_min'], $stats['value_max']),
			'totalValueHint' => 'Suma widełek Mid Risk bieżących wersji, bez ofert anulowanych',
			'winRate' => $stats['win_rate'] === null ? '—' : str_replace('.', ',', (string) $stats['win_rate']) . ' %',
			'winRateHint' => $stats['decided'] > 0
				? sprintf('Zaakceptowane wśród %d ofert z decyzją klienta', $stats['decided'])
				: 'Pojawi się po pierwszej akceptacji lub odrzuceniu',
			'rows' => OfferSummary::rows($result['rows']),
			'result' => $result,
			'filters' => $result['filters'],
			'statuses' => OfferDisplayStatus::options(),
			'types' => OfferVersion::PROJECT_TYPES,
			'canCreate' => $this->policy->canCreate(),
			'viewsAll' => $this->policy->viewsAll(),
		]);
	}
}
