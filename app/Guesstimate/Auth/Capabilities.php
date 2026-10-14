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
	public const MANAGE_SETTINGS = 'gst_manage_settings';
	public const MANAGE_USERS = 'gst_manage_users';
	/** Kosz ofert: widok wszystkich usuniętych i przywracanie (manager, administrator). */
	public const MANAGE_TRASH = 'gst_manage_trash';
	/** Trwałe usuwanie ofert z kosza (tylko administrator). */
	public const DELETE_OFFERS = 'gst_delete_offers';

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
			self::MANAGE_SETTINGS,
			self::MANAGE_USERS,
			self::MANAGE_TRASH,
			self::DELETE_OFFERS,
		];
	}
}
