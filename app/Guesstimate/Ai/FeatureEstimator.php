<?php

namespace App\Guesstimate\Ai;

use App\Guesstimate\Integrations\IntegrationException;
use App\Guesstimate\Offers\OfferVersion;
use App\Guesstimate\Pricing\Decimal;
use App\Guesstimate\Support\Input;

/**
 * Estymator funkcjonalności: rozmowa z AI o jednej funkcjonalności, wynik to propozycja godzin min–max
 * z zakresem, ryzykami i założeniami. Niczego nie zapisuje.
 */
class FeatureEstimator
{
	public const MAX_MESSAGES = 16;
	public const MAX_CHARS = 4000;

	private const TASK = <<<'TXT'
Pomagasz oszacować pracochłonność JEDNEJ funkcjonalności w projekcie WordPress (Sage, ACF, WooCommerce) agencji OhSoFresh.
Godziny dotyczą implementacji (development, konfiguracja, testy) — bez UX/UI, które są liczone osobno.

Zasady:
- przeanalizuj opis; wypisz, czego brakuje do rzetelnej wyceny, i zadaj konkretne pytania (maks. 5),
- zaproponuj zakres prac (lista kroków), widełki godzin min i max, ryzyka i przyjęte założenia,
- przy integracjach zewnętrznych (API, systemy płatności, ERP, rezerwacje itp.) uwzględnij niepewność dokumentacji API,
  dostępów, środowiska testowego i uprawnień; nigdy nie pisz, że sprawdziłeś API lub dokumentację, jeśli jej nie dostałeś,
- jeśli informacji jest za mało, podaj szersze widełki i jasno to zaznacz,
- odpowiadasz po polsku, zwięźle.

Odpowiedz WYŁĄCZNIE obiektem JSON:
{"reply": "krótka odpowiedź dla użytkownika", "missing": ["..."], "questions": ["..."], "scope": ["..."],
 "hours_min": liczba, "hours_max": liczba, "risks": ["..."], "assumptions": ["..."], "name": "proponowana krótka nazwa funkcjonalności"}
TXT;

	public function __construct(private AiService $ai)
	{
	}

	/**
	 * @param  list<array{role: string, content: string}>  $conversation  dotychczasowa rozmowa (user/assistant)
	 */
	public function estimate(OfferVersion $version, array $feature, array $conversation): array
	{
		$messages = [
			['role' => 'system', 'content' => trim($this->ai->instructions() . "\n\n" . self::TASK)],
			['role' => 'user', 'content' => "Kontekst projektu (JSON):\n" . OfferContext::json($version)
				. "\n\nFunkcjonalność do wyceny:\nNazwa: " . ($feature['name'] ?: '(bez nazwy)')
				. "\nOpis: " . ($feature['description'] ?: '(brak opisu)')],
		];

		foreach (self::conversation($conversation) as $message) {
			$messages[] = $message;
		}

		return self::normalize($this->ai->run('estimate', $version->offer_id, $messages, 0.3));
	}

	/**
	 * Rozmowa z przeglądarki: tylko role user/assistant, limit liczby i długości wiadomości.
	 */
	public static function conversation(array $raw): array
	{
		$out = [];

		foreach (array_slice(array_values($raw), -self::MAX_MESSAGES) as $message) {
			$role = ($message['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
			$content = mb_substr(trim((string) ($message['content'] ?? '')), 0, self::MAX_CHARS);

			if ($content !== '') {
				$out[] = ['role' => $role, 'content' => $content];
			}
		}

		return $out;
	}

	public static function normalize(array $data): array
	{
		$list = fn ($key) => array_values(array_slice(array_filter(array_map(fn ($v) => is_scalar($v) ? mb_substr(trim((string) $v), 0, 500) : '', (array) ($data[$key] ?? []))), 0, 12));
		$min = Input::hours(is_numeric($data['hours_min'] ?? null) ? round((float) $data['hours_min'], 2) : null);
		$max = Input::hours(is_numeric($data['hours_max'] ?? null) ? round((float) $data['hours_max'], 2) : null);

		if ($min !== null && $max !== null && Decimal::of($min)->isGreaterThan($max)) {
			[$min, $max] = [$max, $min];
		}

		if ($max !== null && Decimal::of($max)->isGreaterThan(2000)) {
			throw new IntegrationException('AI zaproponowało nierealną liczbę godzin. Doprecyzuj opis i spróbuj ponownie.');
		}

		return [
			'reply' => mb_substr(trim((string) ($data['reply'] ?? '')), 0, 3000),
			'name' => mb_substr(trim((string) ($data['name'] ?? '')), 0, 190),
			'missing' => $list('missing'),
			'questions' => $list('questions'),
			'scope' => $list('scope'),
			'risks' => $list('risks'),
			'assumptions' => $list('assumptions'),
			'hours_min' => $min,
			'hours_max' => $max,
		];
	}
}
