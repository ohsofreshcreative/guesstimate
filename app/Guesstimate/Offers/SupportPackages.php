<?php

namespace App\Guesstimate\Offers;

use App\Guesstimate\Support\Format;
use App\Guesstimate\Support\Input;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Pakiety oferty support (`document.support` wersji): pakiety (nazwa, kolor, opis, cena, dopisek ceny) i wspólna lista
 * punktów. Punkt ma nazwę, opis do legendy, kolumnę legendy i komórkę dla każdego pakietu: „zawiera” albo przekreślony,
 * z opcjonalnym własnym tekstem (np. „Gwarantowany czas reakcji (max. 4h)”). Ceny w groszach.
 * Wzór dla nowych ofert jest w ustawieniach (`support_packages`); oferta dostaje kopię i od tej pory ma własne dane.
 */
final class SupportPackages
{
	public const MAX_PACKAGES = 4;
	public const MAX_POINTS = 24;
	/** Okres rozliczenia pakietu w miesiącach (cena za okres; przy okresie > 1 strona pokazuje też kwotę miesięczną). */
	public const MAX_MONTHS = 12;

	/** Kolory pakietów z palety dokumentu (klasy `text-*` w offer.css). */
	public const COLORS = [
		'orange' => 'Pomarańczowy',
		'flame' => 'Czerwony',
		'magenta' => 'Magenta',
		'violet' => 'Fioletowy',
		'indigo' => 'Niebieski',
	];

	private const LIMITS = ['name' => 60, 'description' => 600, 'price_note' => 60, 'point' => 120, 'label' => 120, 'point_description' => 600];

	/** Teksty stron wzoru (nagłówki: **fragment** = pomarańczowy; wstępy: HTML z edytora). */
	public const TEXTS = [
		'cover_heading' => 160,
		'cover_lead' => 400,
		'packages_heading' => 160,
		'packages_intro' => 4000,
		'legend_heading' => 160,
		'legend_intro' => 4000,
	];

	public const DEFAULTS = [
		'cover_heading' => '**Pakiety Support** – ochrona i rozwój Twojej witryny',
		'cover_lead' => 'Projektujemy i budujemy funkcjonalne strony internetowe na podstawie przemyślanych strategii User Experience.',
		'packages_heading' => 'Pakiety **support**',
		'packages_intro' => '<p>Odkryj nasze pakiety <strong>wsparcia</strong>, <strong>ochrony</strong> i <strong>rozwoju strony internetowej.</strong> Przekonaj się <strong>dlaczego warto</strong> powierzyć nam swoją stronę internetową.</p>',
		'legend_heading' => 'Co zawierają pakiety – **legenda**',
		'legend_intro' => '<p>Każdy kolejny pakiet rozbudowuje <strong>funkcjonalność poprzedniego</strong>, zapewniając kompleksowe wsparcie dla Twojej strony internetowej.</p>',
		'packages' => [
			['name' => 'Bezpieczeństwo', 'color' => 'orange', 'description' => 'Zapewnij stronie codzienną kopię danych, kontrolę nad aktualnością domeny, a także ochronę poprzez zaporę sieci. Przechowujemy kod źródłowy Twojej strony i prowadzimy elementarny monitoring wydajności.', 'price' => 45000, 'months' => 3, 'price_note' => 'netto/co 3 mies.'],
			['name' => 'Niezawodność', 'color' => 'flame', 'description' => 'Dla niezawodnego działania Twojej strony wdrażamy wybrane automatyzacje i przenosimy ją do nowocześniejszego środowiska. Zyskujesz gwarancję reakcji do maksymalnie 4 godzin.', 'price' => 75000, 'months' => 3, 'price_note' => 'netto/co 3 mies.'],
			['name' => 'Rozwój', 'color' => 'indigo', 'description' => 'Wybierz pakiet rozszerzony m.in. o gwarantowany czas reakcji w dni wolne i święta, ultraszybkie środowisko technologiczne oraz zautomatyzowaną kompresję grafik dla jeszcze wyższej wydajności.', 'price' => 110000, 'months' => 3, 'price_note' => 'netto/co 3 mies.'],
		],
		'points' => [
			['name' => 'Codzienna kopia danych', 'description' => 'Codzienny backup plików strony oraz bazy danych.', 'group' => 0, 'cells' => [['on' => true, 'label' => ''], ['on' => true, 'label' => ''], ['on' => true, 'label' => '']]],
			['name' => 'Monitoring aktywności', 'description' => 'W każdym pakiecie monitorujemy ważność certyfikatu SSL, termin wygaśnięcia domeny, czas dostępności serwerów oraz w przypadku pakietów Niezawodność i Rozwój monitorujemy wydajność strony i czas odpowiedzi strony.', 'group' => 0, 'cells' => [['on' => true, 'label' => ''], ['on' => true, 'label' => ''], ['on' => true, 'label' => '']]],
			['name' => 'Ochrona i aktualizacja WordPress', 'description' => 'Zapewniamy podstawowe uwierzytelnianie oraz logowanie nieudanych prób logowania z banowaniem adresu IP. Regularnie i bezpiecznie aktualizujemy core strony i wtyczki.', 'group' => 0, 'cells' => [['on' => true, 'label' => ''], ['on' => true, 'label' => ''], ['on' => true, 'label' => '']]],
			['name' => 'Automatyzacje strony', 'description' => 'Wybrane automatyzacje np. autoodnawianie certyfikatu SSL.', 'group' => 1, 'cells' => [['on' => true, 'label' => ''], ['on' => true, 'label' => ''], ['on' => true, 'label' => '']]],
			['name' => 'Integracje z narzędziami analitycznymi', 'description' => 'Integracja z Google Analytics lub innymi narzędziami analityki internetowej w celu monitorowania ruchu na stronie.', 'group' => 0, 'cells' => [['on' => true, 'label' => ''], ['on' => true, 'label' => ''], ['on' => true, 'label' => '']]],
			['name' => 'Gwarantowany czas reakcji', 'description' => 'W zależności od pakietu – gwarantowana reakcja w dni robocze lub wolne.', 'group' => 1, 'cells' => [['on' => true, 'label' => 'Gwarantowany czas reakcji (max. 4h)'], ['on' => true, 'label' => 'Gwarantowany czas reakcji (max. 3h)'], ['on' => true, 'label' => 'Gwarantowany czas reakcji (max. 2h)']]],
			['name' => 'Nowoczesne środowisko VM', 'description' => 'Osadzenie strony na nowoczesnym środowisku. Zawiera OpenLiteSpeed z obsługą protokołu HTTP/3.', 'group' => 1, 'cells' => [['on' => false, 'label' => ''], ['on' => true, 'label' => ''], ['on' => true, 'label' => '']]],
			['name' => 'Optymalizacja SEO', 'description' => 'Comiesięczna analiza i optymalizacja strony pod kątem SEO, w tym poprawa wydajności, eliminacja błędów oraz dostosowanie do aktualnych wytycznych wyszukiwarek.', 'group' => 1, 'cells' => [['on' => false, 'label' => ''], ['on' => true, 'label' => ''], ['on' => true, 'label' => '']]],
			['name' => 'Prace utrzymaniowe', 'description' => '1h / miesiąc na prace utrzymaniowe w obrębie infrastruktury – np. konfiguracja, instalacja zależności.', 'group' => 1, 'cells' => [['on' => false, 'label' => ''], ['on' => true, 'label' => ''], ['on' => true, 'label' => '']]],
			['name' => 'Kompresja grafik', 'description' => 'Kompresja grafik do formatu AVIF z fallbackiem (WebP/JPG/JPEG/PNG).', 'group' => 2, 'cells' => [['on' => false, 'label' => ''], ['on' => false, 'label' => ''], ['on' => true, 'label' => '']]],
			['name' => 'Rabat na prace rozwojowe', 'description' => 'Rabat w wysokości nawet 10% na prace rozwojowe w obrębie serwisu lub aplikacji.', 'group' => 2, 'cells' => [['on' => false, 'label' => ''], ['on' => false, 'label' => ''], ['on' => true, 'label' => '']]],
			['name' => 'Bezpłatne konsultacje', 'description' => '1h / miesiąc bezpłatnej konsultacji związanej z wybranym aspektem biznesu – np. e-commerce, brandingowa, designerska, marketingowa itp.', 'group' => 2, 'cells' => [['on' => false, 'label' => ''], ['on' => false, 'label' => ''], ['on' => true, 'label' => '']]],
		],
	];

	/**
	 * Wzór z ustawień (brakujące pola uzupełnia DEFAULTS).
	 */
	public static function template(array $settings): array
	{
		$stored = $settings['support_packages'] ?? null;
		$template = is_array($stored) ? array_replace(self::DEFAULTS, $stored) : self::DEFAULTS;

		foreach (self::TEXTS as $key => $max) {
			$template[$key] = (string) $template[$key];
		}

		return self::normalize($template) + array_intersect_key($template, self::TEXTS);
	}

	/**
	 * Dane z bazy w stałym kształcie: komórki punktu zawsze w liczbie pakietów, kolumna legendy w zakresie.
	 *
	 * @return array{packages: list<array>, points: list<array>}
	 */
	public static function normalize(mixed $data): array
	{
		$data = is_array($data) ? $data : [];
		$packages = [];

		foreach (array_slice(array_values((array) ($data['packages'] ?? [])), 0, self::MAX_PACKAGES) as $package) {
			$package = is_array($package) ? $package : [];
			$price = $package['price'] ?? null;
			$packages[] = [
				'name' => self::string($package['name'] ?? '', self::LIMITS['name']),
				'color' => self::color($package['color'] ?? null, count($packages)),
				'description' => self::string($package['description'] ?? '', self::LIMITS['description']),
				'price' => is_int($price) || (is_string($price) && ctype_digit($price)) ? (int) $price : null,
				'months' => self::months($package),
				'price_note' => self::string($package['price_note'] ?? '', self::LIMITS['price_note']),
			];
		}

		$points = [];

		foreach (array_slice(array_values((array) ($data['points'] ?? [])), 0, self::MAX_POINTS) as $point) {
			$point = is_array($point) ? $point : [];
			$points[] = [
				'name' => self::string($point['name'] ?? '', self::LIMITS['point']),
				'description' => self::string($point['description'] ?? '', self::LIMITS['point_description']),
				'group' => self::group($point['group'] ?? null, count($packages)),
				'cells' => self::cells($point['cells'] ?? [], count($packages)),
			];
		}

		return ['packages' => $packages, 'points' => $points];
	}

	/**
	 * Dane z formularza (edytor dokumentu albo ustawienia): ceny jako tekst („450”, „1 100,50”).
	 * „data” = oczyszczone dane także przy błędach (podgląd na żywo).
	 *
	 * @return array{data: array{packages: list<array>, points: list<array>}, errors: array<string, string>}
	 */
	public static function validate(mixed $input): array
	{
		$input = is_array($input) ? $input : [];
		$errors = [];
		$packages = [];
		$rawPackages = array_values((array) ($input['packages'] ?? []));

		if (count($rawPackages) > self::MAX_PACKAGES) {
			$errors['packages'] = sprintf('Najwyżej %d pakiety.', self::MAX_PACKAGES);
			$rawPackages = array_slice($rawPackages, 0, self::MAX_PACKAGES);
		}

		foreach ($rawPackages as $i => $package) {
			$package = is_array($package) ? $package : [];
			$rawPrice = trim((string) (is_scalar($package['price'] ?? null) ? $package['price'] : ''));
			$price = $rawPrice === '' ? null : Input::money($rawPrice);

			if ($rawPrice !== '' && $price === null) {
				$errors["packages.{$i}.price"] = sprintf('Pakiet %d: podaj cenę w złotych, np. 450 albo 1100,50.', $i + 1);
			}

			$rawMonths = array_key_exists('months', $package)
				? trim((string) (is_scalar($package['months']) ? $package['months'] : ''))
				: (string) self::months($package);
			$months = ctype_digit($rawMonths) ? (int) $rawMonths : null;

			if ($months === null || $months < 1 || $months > self::MAX_MONTHS) {
				$errors["packages.{$i}.months"] = sprintf('Pakiet %d: okres rozliczenia to liczba miesięcy od 1 do %d.', $i + 1, self::MAX_MONTHS);
				$months = 1;
			}

			if (isset($package['color']) && ! array_key_exists($package['color'], self::COLORS)) {
				$errors["packages.{$i}.color"] = sprintf('Pakiet %d: wybierz kolor z palety.', $i + 1);
			}

			$packages[] = [
				'name' => self::line($package['name'] ?? '', self::LIMITS['name']),
				'color' => self::color($package['color'] ?? null, $i),
				'description' => self::text($package['description'] ?? '', self::LIMITS['description']),
				'price' => $price,
				'months' => $months,
				'price_note' => self::line($package['price_note'] ?? '', self::LIMITS['price_note']),
			];
		}

		$points = [];
		$rawPoints = array_values((array) ($input['points'] ?? []));

		if (count($rawPoints) > self::MAX_POINTS) {
			$errors['points'] = sprintf('Najwyżej %d punktów.', self::MAX_POINTS);
			$rawPoints = array_slice($rawPoints, 0, self::MAX_POINTS);
		}

		foreach ($rawPoints as $point) {
			$point = is_array($point) ? $point : [];
			$name = self::line($point['name'] ?? '', self::LIMITS['point']);
			$description = self::text($point['description'] ?? '', self::LIMITS['point_description']);
			$cells = [];

			foreach (array_values((array) ($point['cells'] ?? [])) as $cell) {
				$cells[] = ['on' => is_array($cell) && ! empty($cell['on']), 'label' => self::line(is_array($cell) ? ($cell['label'] ?? '') : '', self::LIMITS['label'])];
			}

			// Pusty dodany punkt pomijamy (jak puste pozycje innych list).
			if ($name === '' && $description === '' && ! array_filter($cells, fn ($cell) => $cell['label'] !== '')) {
				continue;
			}

			if ($name === '') {
				$errors['points.' . count($points) . '.name'] = sprintf('Punkt %d: podaj nazwę.', count($points) + 1);
			}

			$points[] = [
				'name' => $name,
				'description' => $description,
				'group' => self::group($point['group'] ?? null, count($packages)),
				'cells' => self::cells($cells, count($packages)),
			];
		}

		return ['data' => ['packages' => $packages, 'points' => $points], 'errors' => $errors];
	}

	/**
	 * Wzór w ustawieniach: pakiety, punkty i teksty stron.
	 *
	 * @return array{data: array, errors: array<string, string>}
	 */
	public static function validateTemplate(mixed $input): array
	{
		$input = is_array($input) ? $input : [];
		['data' => $data, 'errors' => $errors] = self::validate($input);

		foreach (self::TEXTS as $key => $max) {
			$value = str_ends_with($key, '_intro')
				? OfferContent::clean($input[$key] ?? '', headings: true)
				: ($key === 'cover_lead' ? self::text($input[$key] ?? '', $max) : self::line($input[$key] ?? '', $max));

			if (mb_strlen($value) > $max) {
				$errors[$key] = 'Tekst jest za długi.';
			}

			$data[$key] = mb_substr($value, 0, $max);
		}

		if ($data['cover_heading'] === '') {
			$errors['cover_heading'] = 'Podaj nagłówek okładki.';
		}

		return ['data' => $data, 'errors' => $errors + self::problems($data, true)];
	}

	/**
	 * Braki blokujące publikację (i zapis wzoru).
	 *
	 * @return array<string, string>
	 */
	public static function problems(array $support, bool $keys = false): array
	{
		$support = self::normalize($support);
		$problems = [];

		if (! $support['packages']) {
			$problems['packages'] = 'Dodaj co najmniej jeden pakiet support.';
		}

		foreach ($support['packages'] as $i => $package) {
			if ($package['name'] === '') {
				$problems["packages.{$i}.name"] = sprintf('Pakiet %d: podaj nazwę.', $i + 1);
			}

			if ($package['price'] === null) {
				$problems["packages.{$i}.price"] = sprintf('Pakiet %s: podaj cenę.', $package['name'] !== '' ? '„' . $package['name'] . '”' : $i + 1);
			}
		}

		return $keys ? $problems : array_values($problems);
	}

	/**
	 * Najniższa i najwyższa cena pakietów (kolumny list i statystyk).
	 *
	 * @return array{0: int|null, 1: int|null}
	 */
	public static function range(array $support): array
	{
		$prices = array_values(array_filter(array_column(self::normalize($support)['packages'], 'price'), fn ($price) => $price !== null));

		return $prices ? [min($prices), max($prices)] : [null, null];
	}

	/**
	 * Dane do stron dokumentu (snapshot publikacji): ceny jako tekst, lista punktów każdego pakietu i kolumny legendy.
	 */
	public static function present(array $support, string $currency): array
	{
		$support = self::normalize($support);
		$packages = [];

		foreach ($support['packages'] as $i => $package) {
			$packages[] = [
				'name' => $package['name'],
				'color' => $package['color'],
				'description' => $package['description'],
				'price' => $package['price'] !== null ? Format::money($package['price'], $currency) : null,
				'price_note' => $package['price_note'],
				'monthly' => $package['price'] !== null && $package['months'] > 1
					? Format::money(BigDecimal::of($package['price'])->dividedBy($package['months'], 0, RoundingMode::HALF_UP)->toInt(), $currency)
					: null,
				'points' => array_map(fn ($point) => [
					'text' => $point['cells'][$i]['label'] !== '' ? $point['cells'][$i]['label'] : $point['name'],
					'on' => $point['cells'][$i]['on'],
				], $support['points']),
			];
		}

		$legend = array_map(fn ($package) => ['color' => $package['color'], 'items' => []], $support['packages']);

		foreach ($support['points'] as $point) {
			if ($legend && ($point['name'] !== '' || $point['description'] !== '')) {
				$legend[self::legendColumn($point)]['items'][] = ['name' => $point['name'], 'description' => $point['description']];
			}
		}

		return ['packages' => $packages, 'legend' => $legend];
	}

	/**
	 * Kolumna legendy punktu: wybrana ręcznie albo pierwszy pakiet, który go zawiera (bez pakietu — ostatnia kolumna).
	 */
	public static function legendColumn(array $point): int
	{
		if ($point['group'] !== null) {
			return $point['group'];
		}

		foreach ($point['cells'] as $i => $cell) {
			if ($cell['on']) {
				return $i;
			}
		}

		return max(0, count($point['cells']) - 1);
	}

	/**
	 * Stan edytora: ceny jako tekst pola formularza.
	 */
	public static function editorState(array $support): array
	{
		$support = self::normalize($support);
		$support['packages'] = array_map(fn ($package) => ['price' => Format::moneyInput($package['price'])] + $package, $support['packages']);

		return $support;
	}

	/**
	 * Okres w miesiącach. Pakiety zapisane przed dodaniem pola nie mają go: wtedy okres z dopisku („co 3 mies.”), inaczej 1.
	 */
	private static function months(array $package): int
	{
		$months = $package['months'] ?? null;

		if ($months === null && preg_match('/(\d+)\s*mies/u', (string) ($package['price_note'] ?? ''), $match)) {
			$months = $match[1];
		}

		$months = is_int($months) || (is_string($months) && ctype_digit($months)) ? (int) $months : 1;

		return max(1, min(self::MAX_MONTHS, $months));
	}

	private static function cells(mixed $cells, int $count): array
	{
		$cells = array_values(is_array($cells) ? $cells : []);
		$out = [];

		for ($i = 0; $i < $count; $i++) {
			$cell = is_array($cells[$i] ?? null) ? $cells[$i] : [];
			$out[] = ['on' => ! empty($cell['on']), 'label' => self::string($cell['label'] ?? '', self::LIMITS['label'])];
		}

		return $out;
	}

	private static function group(mixed $group, int $count): ?int
	{
		return is_numeric($group) && (int) $group >= 0 && (int) $group < $count ? (int) $group : null;
	}

	private static function color(mixed $color, int $index): string
	{
		return is_string($color) && array_key_exists($color, self::COLORS) ? $color : array_keys(self::COLORS)[$index % count(self::COLORS)];
	}

	private static function string(mixed $value, int $max): string
	{
		return is_scalar($value) ? mb_substr(trim((string) $value), 0, $max) : '';
	}

	private static function line(mixed $value, int $max): string
	{
		return mb_substr((string) (Input::line(is_scalar($value) ? (string) $value : '') ?? ''), 0, $max);
	}

	private static function text(mixed $value, int $max): string
	{
		return mb_substr((string) (Input::text(is_scalar($value) ? (string) $value : '') ?? ''), 0, $max);
	}
}
