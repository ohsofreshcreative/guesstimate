<?php

namespace App\Guesstimate\Offers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Liczenie wyświetleń publicznej oferty.
 *
 * - wyświetlenie to sygnał otwarcia strony, nie potwierdzenie przeczytania,
 * - liczone dopiero po sygnale z przeglądarki (skrypt na stronie), więc podglądy linków w komunikatorach
 *   i większość botów nie są liczone; dodatkowo odrzucamy znane boty i żądania prefetch,
 * - podgląd z panelu (zalogowany zespół) nie jest liczony,
 * - nie zapisujemy pełnego IP: tylko skrót (sól dzienna + skrócony adres + przeglądarka), więc odwiedzin nie da się
 *   powiązać między dniami; ponowne otwarcie przez tę samą osobę w ciągu 30 min liczymy raz.
 */
final class ViewTracker
{
	public const DEDUPE_MINUTES = 30;

	private const BOTS = '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|slack|discord|skype|linkedin|twitter|embedly|quora|pinterest|vkshare|w3c_validator|curl|wget|python|httpclient|java\/|go-http|headless|lighthouse|pagespeed|gtmetrix|monitor|uptime|outlook|microsoft office|teams/i';

	public static function isBot(Request $request): bool
	{
		$ua = (string) $request->userAgent();

		if ($ua === '' || preg_match(self::BOTS, $ua)) {
			return true;
		}

		$purpose = strtolower((string) ($request->header('Sec-Purpose') ?? $request->header('Purpose') ?? $request->header('X-Moz') ?? ''));

		return str_contains($purpose, 'prefetch') || str_contains($purpose, 'prerender');
	}

	/**
	 * @return bool czy wyświetlenie zostało policzone
	 */
	public static function record(Offer $offer, Request $request): bool
	{
		if (self::isBot($request) || is_user_logged_in() || $offer->publication_status !== PublicationStatus::Published || ! $offer->publication_id) {
			return false;
		}

		$hash = self::visitorHash($request);

		return DB::transaction(function () use ($offer, $hash) {
			$recent = OfferView::where('publication_id', $offer->publication_id)
				->where('visitor_hash', $hash)
				->where('viewed_at', '>=', now()->subMinutes(self::DEDUPE_MINUTES))
				->exists();

			if ($recent) {
				return false;
			}

			$now = now();
			OfferView::create(['offer_id' => $offer->id, 'publication_id' => $offer->publication_id, 'visitor_hash' => $hash, 'viewed_at' => $now]);

			DB::table('gst_offers')->where('id', $offer->id)->update([
				'view_count' => DB::raw('view_count + 1'),
				'first_viewed_at' => DB::raw('COALESCE(first_viewed_at, ' . DB::connection()->getPdo()->quote($now->toDateTimeString()) . ')'),
				'last_viewed_at' => $now,
			]);

			return true;
		});
	}

	public static function visitorHash(Request $request): string
	{
		return hash('sha256', implode('|', [wp_salt('nonce'), gmdate('Y-m-d'), self::truncateIp((string) $request->ip()), (string) $request->userAgent()]));
	}

	/**
	 * IPv4 → /24, IPv6 → /48. Pełny adres nie jest nigdzie zapisywany.
	 */
	public static function truncateIp(string $ip): string
	{
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			return preg_replace('/\.\d+$/', '.0', $ip);
		}

		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
			$bin = inet_pton($ip);

			return inet_ntop(substr($bin, 0, 6) . str_repeat("\0", 10));
		}

		return '';
	}
}
