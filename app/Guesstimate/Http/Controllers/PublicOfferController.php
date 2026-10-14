<?php

namespace App\Guesstimate\Http\Controllers;

use App\Guesstimate\Document\DocumentView;
use App\Guesstimate\Offers\Offer;
use App\Guesstimate\Offers\Publication;
use App\Guesstimate\Offers\PublicationStatus;
use App\Guesstimate\Offers\ViewTracker;
use App\Guesstimate\Settings\SettingsRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Publiczna oferta online /offer/{token}/ — bez logowania, tylko opublikowany snapshot.
 */
class PublicOfferController extends Controller
{
	public function __construct(private SettingsRepository $settings)
	{
	}

	public function show(Request $request, string $token): Response
	{
		$offer = $this->find($token);

		if (! $offer) {
			return $this->unavailable(404, 'Nie znaleźliśmy tej oferty', 'Sprawdź, czy link jest kompletny. Jeśli problem się powtarza, skontaktuj się z nami.');
		}

		if ($offer->publication_status !== PublicationStatus::Published) {
			return $this->unavailable(410, 'Ta oferta nie jest już dostępna', 'Oferta została wycofana. Skontaktuj się z nami, a przygotujemy aktualną wersję.');
		}

		$publication = Publication::find($offer->publication_id);

		return $this->render($publication->snapshot, [
			'beacon' => route('gst.public.offer.view', ['token' => $token]),
			'publishedAt' => $publication->published_at,
		]);
	}

	/**
	 * Sygnał wyświetlenia wysyłany przez skrypt strony (navigator.sendBeacon). Zawsze 204.
	 */
	public function view(Request $request, string $token)
	{
		if ($offer = $this->find($token)) {
			ViewTracker::record($offer, $request);
		}

		return response()->noContent();
	}

	public function render(array $snapshot, array $extra = []): Response
	{
		$response = response(view('guesstimate.public.document', array_merge([
			'offer' => DocumentView::fromSnapshot($snapshot),
			'beacon' => null,
			'preview' => false,
			'publishedAt' => null,
		], $extra))->render());

		return $this->secure($response);
	}

	private function unavailable(int $status, string $title, string $text): Response
	{
		return $this->secure(response(view('guesstimate.public.unavailable', ['title' => $title, 'text' => $text])->render(), $status));
	}

	private function find(string $token): ?Offer
	{
		if (! preg_match('/^[A-Za-z0-9_-]{32}$/', $token)) {
			return null;
		}

		return Offer::where('public_token', $token)->first();
	}

	private function secure(Response $response): Response
	{
		$response->headers->set('Referrer-Policy', 'no-referrer');
		$response->headers->set('X-Content-Type-Options', 'nosniff');
		$response->headers->set('X-Frame-Options', 'SAMEORIGIN');

		return $response;
	}
}
