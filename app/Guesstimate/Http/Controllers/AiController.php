<?php

namespace App\Guesstimate\Http\Controllers;

use App\Guesstimate\Ai\ContentWriter;
use App\Guesstimate\Ai\FeatureEstimator;
use App\Guesstimate\Integrations\IntegrationException;
use App\Guesstimate\Offers\Offer;
use App\Guesstimate\Offers\OfferPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpointy asystenta AI (JSON). Wymagają prawa edycji oferty; zwracają propozycje, nic nie zapisują.
 */
class AiController extends Controller
{
	public function __construct(private OfferPolicy $policy)
	{
	}

	public function estimate(Request $request, Offer $offer, FeatureEstimator $estimator): JsonResponse
	{
		$this->policy->authorizeEdit($offer);

		try {
			$result = $estimator->estimate($offer->currentVersion, [
				'name' => mb_substr(trim((string) $request->input('name')), 0, 190),
				'description' => mb_substr(trim((string) $request->input('description')), 0, FeatureEstimator::MAX_CHARS),
			], (array) $request->input('messages', []));
		} catch (IntegrationException $e) {
			return response()->json(['error' => $e->getMessage()], 422);
		}

		return response()->json($result);
	}

	public function content(Request $request, Offer $offer, ContentWriter $writer): JsonResponse
	{
		$this->policy->authorizeEdit($offer);

		try {
			$result = $writer->write(
				$offer->currentVersion,
				(string) $request->input('action'),
				(string) $request->input('target', 'all'),
				(string) $request->input('prompt', ''),
				(array) $request->input('content', []),
				(array) $request->input('texts', []),
				(int) $request->input('target_chars', 0),
			);
		} catch (IntegrationException $e) {
			return response()->json(['error' => $e->getMessage()], 422);
		}

		return response()->json($result);
	}
}
