<?php

namespace App\Guesstimate\Http\Controllers;

use App\Guesstimate\Offers\Offer;
use App\Guesstimate\Offers\OfferPolicy;
use App\Guesstimate\Offers\PublicationService;
use App\Guesstimate\Offers\PublicOffer;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Publikacja oferty online z panelu: podgląd (nie jest liczony jako wyświetlenie), publikacja, wycofanie, przywrócenie.
 */
class PublicationController extends Controller
{
	public function __construct(private OfferPolicy $policy, private PublicationService $publications)
	{
	}

	public function preview(Offer $offer, PublicOfferController $public)
	{
		$this->policy->authorizeView($offer);
		$version = $offer->currentVersion;
		abort_unless($version->isBudget() && $version->result, 409, 'Podgląd jest dostępny po poprawnej kalkulacji w nowym modelu.');

		return $public->render(PublicOffer::build($offer, $version), ['preview' => true]);
	}

	public function publish(Request $request, Offer $offer)
	{
		$this->policy->authorizeEdit($offer);
		$back = $this->back($request, $offer);

		try {
			$publication = $this->publications->publish($offer);
		} catch (InvalidArgumentException $e) {
			return $this->redirectWith($back, $e->getMessage(), 'error');
		}

		$message = match (true) {
			! empty($publication->duplicate) => 'Oferta jest już opublikowana w tej wersji — nie utworzono drugiej publikacji.',
			$publication->number > 1 => sprintf('Opublikowano nową wersję oferty online (wersja %d). Link się nie zmienił.', $publication->version_no),
			default => 'Oferta jest opublikowana. Skopiuj link i wyślij go klientowi.',
		};

		// Z edytora dokumentu: dopiero po udanej publikacji — przejście do szczegółów oferty (link, historia publikacji).
		// Przy błędzie (wyżej) użytkownik wraca do edytora z komunikatem.
		if ($request->input('after') === 'summary') {
			$back = route('gst.offers.show', $offer);
		}

		return $this->redirectWith($back, $message);
	}

	public function withdraw(Request $request, Offer $offer)
	{
		$this->policy->authorizeEdit($offer);

		try {
			$this->publications->withdraw($offer);
		} catch (InvalidArgumentException $e) {
			return $this->redirectWith($this->back($request, $offer), $e->getMessage(), 'error');
		}

		return $this->redirectWith($this->back($request, $offer), 'Wycofano ofertę online. Link pokazuje teraz informację, że oferta nie jest dostępna.');
	}

	public function restore(Request $request, Offer $offer)
	{
		$this->policy->authorizeEdit($offer);

		try {
			$this->publications->restore($offer);
		} catch (InvalidArgumentException $e) {
			return $this->redirectWith($this->back($request, $offer), $e->getMessage(), 'error');
		}

		return $this->redirectWith($this->back($request, $offer), 'Przywrócono publikację pod tym samym linkiem (bez zmian treści i cen).');
	}

	private function back(Request $request, Offer $offer): string
	{
		return match ($request->input('back')) {
			'wizard' => route('gst.offers.wizard', ['offer' => $offer, 'step' => 6]),
			'document' => route('gst.offers.document', $offer),
			default => route('gst.offers.show', $offer),
		};
	}
}
