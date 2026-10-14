<?php

namespace App\Guesstimate\Support;

use App\Guesstimate\Auth\Capabilities;

/**
 * Jedno źródło prawdy o modułach aplikacji: nawigacja, uprawnienia i opisy stron.
 */
final class Modules
{
	/**
	 * @return array<string, array{label: string, route: string, icon: string, capability: string, description: string}>
	 */
	public static function all(): array
	{
		return [
			'dashboard' => [
				'label' => 'Pulpit',
				'route' => 'gst.dashboard',
				'icon' => 'dashboard',
				'capability' => Capabilities::ACCESS_APP,
				'description' => 'Stan ofert agencji i ostatnie wyceny.',
			],
			'offers' => [
				'label' => 'Oferty',
				'route' => 'gst.offers.index',
				'icon' => 'offers',
				'capability' => Capabilities::ACCESS_APP,
				'description' => 'Archiwum wycen z wersjami, statusami i historią zmian.',
			],
			'clients' => [
				'label' => 'Klienci',
				'route' => 'gst.clients.index',
				'icon' => 'clients',
				'capability' => Capabilities::MANAGE_CLIENTS,
				'description' => 'Firmy, osoby kontaktowe i historia przygotowanych dla nich wycen.',
			],
			'features' => [
				'label' => 'Funkcjonalności',
				'route' => 'gst.features.index',
				'icon' => 'features',
				'capability' => Capabilities::MANAGE_LIBRARY,
				'description' => 'Biblioteka funkcjonalności z widełkami godzin, wybieranych w ofertach.',
			],
			'integrations' => [
				'label' => 'Moje integracje',
				'route' => 'gst.integrations.index',
				'icon' => 'integrations',
				'capability' => Capabilities::CREATE_OFFERS,
				'description' => 'Twoje połączenie z ClickUp: listy z klientami, mapowanie pól i synchronizacja firm.',
			],
			'settings' => [
				'label' => 'Ustawienia',
				'route' => 'gst.settings.index',
				'icon' => 'settings',
				'capability' => Capabilities::MANAGE_SETTINGS,
				'description' => 'Stawki godzinowe, zakresy UX, etapy, typy podstron i numeracja ofert.',
			],
		];
	}

	/**
	 * @return array{label: string, route: string, icon: string, capability: string, description: string}|null
	 */
	public static function find(string $key): ?array
	{
		return self::all()[$key] ?? null;
	}
}
