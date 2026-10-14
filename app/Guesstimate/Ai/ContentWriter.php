<?php

namespace App\Guesstimate\Ai;

use App\Guesstimate\Integrations\IntegrationException;
use App\Guesstimate\Offers\OfferContent;
use App\Guesstimate\Offers\OfferVersion;
use App\Guesstimate\Support\Input;

/**
 * Generowanie treści oferty (sekcje „Jak rozumiemy Twoją sytuację?” i „Jak osiągnąć ten cel?”).
 * Zwraca propozycję — zastąpienie istniejącej treści wymaga potwierdzenia w przeglądarce.
 */
class ContentWriter
{
	public const ACTIONS = ['generate_all', 'regenerate', 'improve', 'shorten', 'expand', 'fit'];

	/** Krótkie teksty stron dokumentu (edytor wizualny), które AI może zredagować. */
	public const TEXT_FIELDS = [
		'cover_lead' => ['label' => 'krótki opis na okładce oferty', 'max' => 400],
		'extras_intro' => ['label' => 'wstęp do strony „Usługi dodatkowe”', 'max' => 800],
		'references_intro' => ['label' => 'wstęp do strony „Realizacje”', 'max' => 600],
		'next_steps_note' => ['label' => 'wyróżniona informacja pod kolejnymi krokami', 'max' => 300],
	];

	private const FORMAT = <<<'TXT'
Formatowanie: HTML wyłącznie z <p>, <strong>, <em>, <ul>, <ol>, <li>, <a>. Bez nagłówków <h1>-<h6>, bez stylów i klas.
Styl: profesjonalny, konkretny, naturalny, biznesowy. Bez marketingowego nadęcia i superlatyw. Nie wymyślaj faktów, liczb, wyników ani nazw, których nie ma w danych.
Sekcja „Jak rozumiemy Twoją sytuację?”: kim jest klient i jaki ma problem/cel (na podstawie danych), co wiemy po rozmowie (lista), czego potrzebuje (lista).
Sekcja „Jak osiągnąć ten cel?”: 3–5 bloków; każdy ma krótki nagłówek (do 70 znaków) i 2–4 zdania opisu, jak rozwiązujemy dany obszar
(np. technologia WordPress + Sage, UX/UI, wygodne zarządzanie treścią, SEO, integracje z listy funkcjonalności).
TXT;

	public function __construct(private AiService $ai)
	{
	}

	/**
	 * @param  string  $target  'all' | 'situation' | 'block:<n>' | 'text:<pole>'
	 * @param  array  $texts  aktualne krótkie teksty dokumentu (cel 'text:<pole>')
	 * @param  int  $targetChars  docelowa długość dla akcji 'fit' (dopasowanie do strony A4)
	 */
	public function write(OfferVersion $version, string $action, string $target, string $prompt, array $current, array $texts = [], int $targetChars = 0): array
	{
		if (! in_array($action, self::ACTIONS, true)) {
			throw new IntegrationException('Nieznana akcja.');
		}

		if (str_starts_with($target, 'text:')) {
			return $this->text($version, $action, substr($target, 5), (string) ($texts[substr($target, 5)] ?? ''), $prompt);
		}

		if ($action === 'fit' && $target !== 'situation') {
			throw new IntegrationException('Dopasowanie do strony dotyczy sekcji „Jak rozumiemy Twoją sytuację?”.');
		}

		$prompt = mb_substr(trim($prompt), 0, 3000);
		$content = OfferContent::normalize($current);
		$section = $this->section($content, $target);

		$task = match ($action) {
			'generate_all' => 'Napisz obie sekcje oferty od nowa. Liczba bloków: ' . max(3, min(5, count($content['blocks']))) . '. Odpowiedz JSON: {"situation": "<html>", "blocks": [{"title": "...", "html": "<html>"}]}',
			'regenerate' => 'Napisz od nowa tylko wskazaną sekcję. Odpowiedz JSON: ' . $this->shape($target),
			'improve' => "Popraw wskazany tekst: klarowność, konkret, styl; zachowaj sens i fakty. Odpowiedz JSON: " . $this->shape($target),
			'shorten' => "Skróć wskazany tekst o ok. 30–40%, zachowując najważniejsze informacje. Odpowiedz JSON: " . $this->shape($target),
			'expand' => "Rozwiń wskazany tekst o konkretne informacje wynikające z danych projektu (bez wymyślania faktów). Odpowiedz JSON: " . $this->shape($target),
			'fit' => sprintf(
				'Tekst musi zmieścić się na jednej stronie A4 poziomo. Skróć go do najwyżej ok. %d znaków (obecnie ok. %d), zachowując wszystkie kluczowe informacje, fakty, nazwy i strukturę (akapit wstępu i listy). Usuń powtórzenia i ogólniki, łącz podobne punkty. Odpowiedz JSON: %s',
				max(200, $targetChars),
				mb_strlen(trim(wp_strip_all_tags($content['situation']))),
				$this->shape($target)
			),
		};

		$user = "Dane projektu (JSON):\n" . OfferContext::json($version);

		if ($prompt !== '') {
			$user .= "\n\nWskazówki od opiekuna oferty:\n" . $prompt;
		}

		if ($action !== 'generate_all' && $section !== null) {
			$user .= "\n\nAktualna treść sekcji:\n" . wp_json_encode($section, JSON_UNESCAPED_UNICODE);
		}

		$messages = [
			['role' => 'system', 'content' => trim($this->ai->instructions() . "\n\n" . self::FORMAT)],
			['role' => 'user', 'content' => $user . "\n\nZadanie: " . $task],
		];

		$data = $this->ai->run('content', $version->offer_id, $messages, 0.5);

		return $action === 'generate_all' ? $this->all($data) : $this->one($data, $target);
	}

	/**
	 * Redakcja krótkiego tekstu strony dokumentu (zwykły tekst, bez HTML).
	 */
	private function text(OfferVersion $version, string $action, string $field, string $current, string $prompt): array
	{
		$spec = self::TEXT_FIELDS[$field] ?? null;

		if ($spec === null || $action === 'generate_all' || $action === 'fit') {
			throw new IntegrationException('Tego pola nie można redagować z AI.');
		}

		$task = match ($action) {
			'regenerate' => 'Napisz ten tekst od nowa',
			'improve' => 'Popraw ten tekst: klarowność, konkret, styl; zachowaj sens i fakty',
			'shorten' => 'Skróć ten tekst o ok. 30–40%, zachowując najważniejsze informacje',
			'expand' => 'Rozwiń ten tekst o konkretne informacje z danych projektu (bez wymyślania faktów)',
		};

		$user = "Dane projektu (JSON):\n" . OfferContext::json($version)
			. "\n\nPole: " . $spec['label'] . sprintf(' (zwykły tekst, bez HTML, do %d znaków; **fragment** oznacza wyróżnienie — możesz go zachować).', $spec['max'])
			. ($current !== '' ? "\n\nAktualny tekst:\n" . mb_substr($current, 0, 2000) : '')
			. ($prompt !== '' ? "\n\nWskazówki od opiekuna oferty:\n" . mb_substr(trim($prompt), 0, 3000) : '')
			. "\n\nZadanie: " . $task . '. Odpowiedz JSON: {"text": "..."}';

		$data = $this->ai->run('content', $version->offer_id, [
			['role' => 'system', 'content' => trim($this->ai->instructions() . "\n\n" . self::FORMAT)],
			['role' => 'user', 'content' => $user],
		], 0.5);

		$text = mb_substr(trim((string) (Input::text($data['text'] ?? '') ?? '')), 0, $spec['max']);

		if ($text === '') {
			throw new IntegrationException('AI zwróciło pustą treść. Spróbuj ponownie.');
		}

		return ['text' => $text];
	}

	private function section(array $content, string $target): ?array
	{
		if ($target === 'situation') {
			return ['html' => $content['situation']];
		}

		if (preg_match('/^block:(\d)$/', $target, $m) && isset($content['blocks'][(int) $m[1]])) {
			return $content['blocks'][(int) $m[1]];
		}

		return null;
	}

	private function shape(string $target): string
	{
		return $target === 'situation' ? '{"html": "<html>"}' : '{"title": "...", "html": "<html>"}';
	}

	private function all(array $data): array
	{
		$blocks = array_map(fn ($b) => ['title' => mb_substr((string) Input::line($b['title'] ?? ''), 0, 190), 'html' => OfferContent::clean($b['html'] ?? '')], array_slice((array) ($data['blocks'] ?? []), 0, OfferContent::MAX_BLOCKS));

		if (count($blocks) < OfferContent::MIN_BLOCKS || OfferContent::isEmpty(OfferContent::clean($data['situation'] ?? ''))) {
			throw new IntegrationException('AI zwróciło niepełną treść. Spróbuj ponownie albo doprecyzuj wskazówki.');
		}

		return ['situation' => OfferContent::clean($data['situation'] ?? ''), 'blocks' => array_values($blocks)];
	}

	private function one(array $data, string $target): array
	{
		$html = OfferContent::clean($data['html'] ?? '');

		if (OfferContent::isEmpty($html)) {
			throw new IntegrationException('AI zwróciło pustą treść. Spróbuj ponownie.');
		}

		return $target === 'situation'
			? ['html' => $html]
			: ['title' => mb_substr((string) Input::line($data['title'] ?? ''), 0, 190), 'html' => $html];
	}
}
