<?php

namespace App\Guesstimate\Contracts;

/**
 * Zmienne dostępne w szablonach. Wartości daje ContractValues: ze snapshotu albo wpisane ręcznie w umowie.
 * Kwoty umowy wynikają z harmonogramu płatności, a kwoty oferty to widełki i tak są nazwane (decyzja 4).
 */
final class TemplateVariables
{
	public const KEY = '/^[a-z][a-z_]*\.[a-z][a-z_]*$/';

	public const GROUPS = [
		'Klient' => [
			'client.company_name' => 'Nazwa firmy',
			'client.address' => 'Adres klienta',
			'client.nip' => 'NIP',
			'client.representative' => 'Reprezentant klienta',
			'client.contact_name' => 'Osoba kontaktowa',
			'client.email' => 'E-mail',
			'client.phone' => 'Telefon',
			'client.website_url' => 'Adres WWW',
		],
		'Oferta' => [
			'offer.number' => 'Numer oferty',
			'offer.date' => 'Data oferty',
			'offer.title' => 'Nazwa projektu',
			'offer.project_type' => 'Rodzaj projektu',
			'offer.version_no' => 'Wersja oferty',
			'offer.description' => 'Opis projektu',
			'offer.ux_scope' => 'Zakres UX',
			'offer.pages' => 'Mapa strony',
			'offer.features' => 'Funkcjonalności',
			'offer.variant' => 'Wybrany wariant',
			'offer.amount_variant' => 'Wycena wybranego wariantu (od–do)',
			'offer.amount_range' => 'Wycena wszystkich wariantów (od–do)',
		],
		'Wykonawca' => [
			'contractor.company_name' => 'Nazwa wykonawcy',
			'contractor.address' => 'Adres wykonawcy',
			'contractor.nip' => 'NIP wykonawcy',
			'contractor.representative' => 'Reprezentant wykonawcy',
		],
		'Umowa i terminy' => [
			'contract.amount' => 'Kwota umowy netto',
			'contract.amount_vat' => 'VAT od kwoty umowy',
			'contract.amount_gross' => 'Kwota umowy brutto',
			'contract.vat_rate' => 'Stawka VAT',
			'contract.deposit' => 'Zadatek brutto',
			'payments.table' => 'Harmonogram płatności (tabela)',
			'terms.signed_at' => 'Data zawarcia umowy',
			'terms.place' => 'Miejsce zawarcia umowy',
			'terms.start' => 'Termin rozpoczęcia',
			'terms.end' => 'Termin zakończenia',
		],
		'Strona' => [
			'page.number' => 'Numer strony',
			'page.count' => 'Liczba stron',
		],
	];

	/**
	 * @return array<string, string>
	 */
	public static function all(): array
	{
		return array_merge(...array_values(self::GROUPS));
	}

	/**
	 * @return list<string>
	 */
	public static function used(string $html): array
	{
		preg_match_all('/\bdata-var="([^"]*)"/', $html, $matches);

		return array_values(array_unique($matches[1]));
	}

	/**
	 * @return list<string>
	 */
	public static function unknown(string $html): array
	{
		return array_values(array_diff(self::used($html), array_keys(self::all())));
	}
}
