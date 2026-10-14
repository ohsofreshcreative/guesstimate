<?php

namespace App\Guesstimate\Settings;

/**
 * Wartości domyślne ustawień. Są punktem startowym — administrator zmienia je w panelu ustawień.
 * Zapisane oferty mają własną kopię i nie zależą od tych wartości.
 */
final class Defaults
{
	public const STAGE_KEYS = ['discovery', 'ux', 'ui', 'dev', 'deploy', 'other'];

	public const AI_INSTRUCTIONS = <<<'TXT'
Jesteś doświadczonym konsultantem agencji OhSoFresh Creative, która projektuje i wdraża strony oraz sklepy na WordPressie.
Piszesz po polsku, profesjonalnie, konkretnie i naturalnie, tonem biznesowym. Bez marketingowego nadęcia, bez superlatyw i pustych haseł.
Opierasz się wyłącznie na przekazanych danych. Nie wymyślasz faktów o kliencie, liczb, wyników ani referencji.
Jeśli czegoś nie wiesz, piszesz ogólniej albo wskazujesz, co trzeba ustalić.
TXT;

	public static function all(): array
	{
		return [
			'hourly_rates' => ['low' => 15000, 'mid' => 18000, 'high' => 20000],
			'currency' => 'PLN',
			'vat_rate' => '23',
			'ux_scopes' => [
				'optimized' => ['label' => 'Zoptymalizowany UX', 'min' => '16.00', 'max' => '16.00'],
				'full' => ['label' => 'Pełny UX', 'min' => '32.00', 'max' => '32.00'],
			],
			'default_calc_model' => 'hourly',
			'content_stage' => 'deploy',
			'rounding' => ['hours_step' => '0.25', 'price_step' => 100, 'part_step' => 100],
			// Model budżetowy (v2): rodzaje projektu z jednostkami kalkulacyjnymi (calc.xlsx D22 / C26),
			// godziny na jednostkę (podstronę), warsztaty Discovery w cenie i podział reszty.
			'project_types' => [
				'website' => ['label' => 'Strona internetowa', 'units' => '0'],
				'catalog' => ['label' => 'Katalog produktów', 'units' => '5'],
				'woocommerce' => ['label' => 'Sklep internetowy', 'units' => '8'],
				'landing' => ['label' => 'Landing page', 'units' => '0'],
				'webapp' => ['label' => 'Aplikacja webowa', 'units' => '0'],
				'other' => ['label' => 'Inny', 'units' => '0'], // „Inny”: liczba jednostek wpisywana w ofercie
			],
			'page_hours' => ['ui_min' => '2.00', 'ui_max' => '3.00', 'dev_min' => '2.00', 'dev_max' => '3.00'],
			'workshops_amount' => 200000,
			'budget_split' => ['ux' => '20', 'ui' => '30', 'dev' => '50'],
			'ai' => [
				'model' => 'gpt-4.1-mini',
				'instructions' => self::AI_INSTRUCTIONS,
				'daily_limit' => 40,
				'timeout' => 45,
			],
			// Oferta online jako dokument stron A4 poziomo (wzór: FARMAK × OhSoFresh).
			'document' => [
				'cover_heading' => 'Strona internetowa, która **przyspieszy** rozwój Twojej firmy',
				'cover_lead' => 'Projektujemy i budujemy funkcjonalne strony internetowe na podstawie przemyślanych strategii User Experience.',
				'website' => 'www.ohsofresh.pl',
				'validity_days' => 30,
				'price_includes' => [
					'Opracowanie architektury informacji zgodnie z mapą strony',
					'Zaprojektowanie podstron i ich struktury w oparciu o UX',
					'Przygotowanie serwisu w oparciu o WordPress Sage 11',
					'Optymalizacja panelu administracyjnego WordPress',
					'Responsywność – poprawne działanie na urządzeniach mobilnych i desktopowych',
					'Wsparcie project managera',
					'Integracja z Google Analytics',
					'Testy jakościowe oraz zmiany po nich',
					'Instrukcja obsługi strony oraz szkolenie',
					'Czynności przedwdrożeniowe oraz wdrożenie na serwer',
				],
				'extras_intro' => 'W ramach współpracy oferujemy również copywriting – profesjonalne tworzenie treści dopasowanych do specyfiki Twojej branży i celów strony. Dodatkowo możemy przygotować stronę z możliwością wyboru wersji językowej, co ułatwia dotarcie do szerszego grona odbiorców.',
				'extras' => [
					['name' => 'Copywriting – przygotowanie treści na stronę', 'description' => "Analiza branży i określenie głównego przekazu informacji.\nOpracowanie contentu uwzględniającego słowa kluczowe.", 'price' => '100–120 zł netto / 1000 znaków ze spacjami'],
					['name' => 'Dodatkowa wersja językowa', 'description' => "Dostosowanie strony do dodatkowej wersji językowej.\nNie uwzględnia przygotowania treści w języku obcym.", 'price' => '+15% kwoty bazowej za każdą wersję językową'],
					['name' => 'Integracja wtyczki GTranslate', 'description' => 'Instalacja i konfiguracja rozwiązania do wymaganych wersji językowych.', 'price' => '1 000 zł netto'],
				],
				'references_intro' => 'Zapewniamy nie tylko estetyczne strony internetowe, ale przede wszystkim **efektywne narzędzia biznesowe**. Niezależnie od branży czy wielkości firmy, nasze projekty przynoszą realne rezultaty.',
				'next_steps' => [
					['title' => 'Konsultacja i omówienie szczegółów oferty', 'note' => '(online lub telefoniczne)'],
					['title' => 'Podjęcie decyzji i akceptacja współpracy', 'note' => '(do 3 dni po spotkaniu)'],
					['title' => 'Podpisanie umowy i ustalenie harmonogramu prac', 'note' => '(1–3 dni)'],
					['title' => 'Rozpoczęcie prac nad stroną', 'note' => ''],
				],
				'next_steps_note' => '**Jesteśmy dostępni na każdym etapie**, aby odpowiedzieć na pytania i pomóc w podjęciu decyzji.',
				'contact' => ['name' => '', 'role' => '', 'phone' => '', 'email' => ''],
				'backgrounds' => [],
				'logo_attachment_id' => null,
			],
			'clickup' => [
				'team_id' => '',
				'list_ids' => [],
				'mapping' => ['company_name' => 'name', 'website_url' => '', 'contact_name' => '', 'email' => '', 'phone' => '', 'nip' => ''],
			],
			'numbering' => ['prefix' => 'OSF', 'padding' => 4],
			'stages' => [
				['key' => 'discovery', 'name' => 'Warsztaty Discovery', 'enabled' => true, 'in_pool' => false, 'percent' => '0', 'fixed_amount' => 200000],
				['key' => 'ux', 'name' => 'UX Design', 'enabled' => true, 'in_pool' => true, 'percent' => '20', 'fixed_amount' => null],
				['key' => 'ui', 'name' => 'UI Design', 'enabled' => true, 'in_pool' => true, 'percent' => '30', 'fixed_amount' => null],
				['key' => 'dev', 'name' => 'Development', 'enabled' => true, 'in_pool' => true, 'percent' => '50', 'fixed_amount' => null],
				['key' => 'deploy', 'name' => 'Wdrożenie', 'enabled' => true, 'in_pool' => false, 'percent' => '0', 'fixed_amount' => null],
				['key' => 'other', 'name' => 'Pozostałe', 'enabled' => true, 'in_pool' => false, 'percent' => '0', 'fixed_amount' => null],
			],
		];
	}

	/**
	 * Przykładowe typy podstron z widełkami godzin (do edycji w ustawieniach).
	 */
	public static function pageTypes(): array
	{
		// [nazwa, rodzaj, UI min, UI max, Dev min, Dev max, treść min, treść max (na sztukę)]
		return [
			['Strona główna', 'unique', 6, 8, 8, 12, 1, 2],
			['Podstrona indywidualna', 'unique', 3, 5, 4, 6, 0.5, 1],
			['Podstrona standardowa', 'template', 2, 3, 2, 3, 0.25, 0.5],
			['Archiwum wpisów', 'template', 2, 3, 3, 4, 0, 0],
			['Wpis blogowy', 'template', 2, 3, 2, 4, 0.25, 0.5],
			['Archiwum produktów', 'template', 3, 4, 4, 6, 0, 0],
			['Kategoria produktów', 'template', 2, 3, 3, 4, 0.1, 0.25],
			['Karta produktu', 'template', 4, 6, 8, 12, 0.1, 0.2],
			['Koszyk', 'template', 2, 3, 3, 5, 0, 0],
			['Checkout', 'template', 2, 3, 4, 6, 0, 0],
			['Konto użytkownika', 'template', 3, 4, 4, 6, 0, 0],
			['Inny', 'unique', 2, 4, 2, 4, 0.25, 0.5],
		];
	}

	/**
	 * Przykładowa biblioteka funkcjonalności: kategoria => [[nazwa, min, max, etap]].
	 */
	public static function features(): array
	{
		return [
			'Wielojęzyczność' => [['Polylang', 6, 10, 'dev'], ['GTranslate', 2, 4, 'dev']],
			'WordPress' => [['Blog', 4, 8, 'dev'], ['Wyszukiwarka', 3, 6, 'dev'], ['Zaawansowane filtrowanie', 12, 24, 'dev'], ['Custom Post Types', 4, 8, 'dev']],
			'WooCommerce' => [
				['Konfiguracja sklepu', 8, 16, 'dev'], ['Płatności', 4, 8, 'dev'], ['Dostawy', 4, 8, 'dev'], ['InPost', 4, 8, 'dev'],
				['Integracja BaseLinker', 8, 16, 'dev'], ['Integracja ERP', 24, 48, 'dev'], ['Konta klientów', 4, 8, 'dev'],
			],
			'Formularze' => [['Formularze CF7', 2, 4, 'dev']],
			'Integracje' => [['Integracja API', 12, 32, 'dev'], ['Newsletter', 3, 6, 'dev']],
			'Animacje' => [['Animacje GSAP', 8, 16, 'dev']],
			'Konta użytkowników' => [['Rejestracja i logowanie', 6, 12, 'dev']],
			'SEO' => [['Podstawowa konfiguracja SEO', 3, 6, 'deploy']],
			'Analityka' => [['GA4 i Google Tag Manager', 2, 4, 'deploy']],
			'Pozostałe' => [['System rezerwacji', 24, 48, 'dev'], ['Migracja danych', 8, 24, 'deploy'], ['Import produktów', 6, 16, 'deploy']],
		];
	}
}
