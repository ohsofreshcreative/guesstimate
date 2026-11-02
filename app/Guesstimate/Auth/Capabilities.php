<?php

namespace App\Guesstimate\Auth;

/**
 * Capabilities aplikacji Guesstimate (rejestrowane w rolach WordPressa).
 */
final class Capabilities
{
	public const ACCESS_APP = 'gst_access_app';
	public const CREATE_OFFERS = 'gst_create_offers';
	public const VIEW_ALL_OFFERS = 'gst_view_all_offers';
	public const EDIT_ALL_OFFERS = 'gst_edit_all_offers';
	public const CREATE_CLIENTS = 'gst_create_clients';
	public const MANAGE_CLIENTS = 'gst_manage_clients';
	public const MANAGE_LIBRARY = 'gst_manage_library';
	/** Biblioteka grafik: cudze pliki i foldery (manager, administrator). */
	public const MANAGE_MEDIA = 'gst_manage_media';
	public const MANAGE_SETTINGS = 'gst_manage_settings';
	public const MANAGE_USERS = 'gst_manage_users';
	/** Kosz ofert: widok wszystkich usuniętych i przywracanie (manager, administrator). */
	public const MANAGE_TRASH = 'gst_manage_trash';
	/** Trwałe usuwanie ofert z kosza (tylko administrator). */
	public const DELETE_OFFERS = 'gst_delete_offers';
	/** Zatwierdzanie umów (manager, administrator; propozycja z decyzji 8). */
	public const APPROVE_CONTRACTS = 'gst_approve_contracts';
	/** Szablony umów (manager, administrator; propozycja z decyzji 8). */
	public const MANAGE_CONTRACT_TEMPLATES = 'gst_manage_contract_templates';

	/**
	 * @return list<string>
	 */
	public static function all(): array
	{
		return [
			self::ACCESS_APP,
			self::CREATE_OFFERS,
			self::VIEW_ALL_OFFERS,
			self::EDIT_ALL_OFFERS,
			self::CREATE_CLIENTS,
			self::MANAGE_CLIENTS,
			self::MANAGE_LIBRARY,
			self::MANAGE_MEDIA,
			self::MANAGE_SETTINGS,
			self::MANAGE_USERS,
			self::MANAGE_TRASH,
			self::DELETE_OFFERS,
			self::APPROVE_CONTRACTS,
			self::MANAGE_CONTRACT_TEMPLATES,
		];
	}
}
