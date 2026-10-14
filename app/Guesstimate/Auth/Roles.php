<?php

namespace App\Guesstimate\Auth;

use WP_User;

/**
 * Role aplikacji oparte na rolach WordPressa.
 *
 * Definicje są czystymi danymi (testowalne bez WP), a sync() zapisuje je w bazie.
 * Podbij VERSION po każdej zmianie definicji, żeby role zsynchronizowały się przy następnym żądaniu.
 */
final class Roles
{
	public const ADMINISTRATOR = 'administrator';
	public const MANAGER = 'gst_manager';
	public const EMPLOYEE = 'gst_employee';

	public const VERSION = 3;
	public const VERSION_OPTION = 'gst_roles_version';

	/** Uprawnienia WordPressa: przesyłanie zdjęć stron oferty do biblioteki mediów (edytor dokumentu). */
	private const MEDIA = ['upload_files'];

	/**
	 * @return array<string, array{label: string, capabilities: list<string>, wordpress?: list<string>}>
	 */
	public static function definitions(): array
	{
		return [
			self::ADMINISTRATOR => [
				'label' => 'Administrator',
				'capabilities' => Capabilities::all(),
			],
			self::MANAGER => [
				'label' => 'Manager',
				'capabilities' => [
					Capabilities::ACCESS_APP,
					Capabilities::CREATE_OFFERS,
					Capabilities::VIEW_ALL_OFFERS,
					Capabilities::EDIT_ALL_OFFERS,
					Capabilities::CREATE_CLIENTS,
					Capabilities::MANAGE_CLIENTS,
					Capabilities::MANAGE_TRASH,
				],
				'wordpress' => self::MEDIA,
			],
			self::EMPLOYEE => [
				'label' => 'Pracownik',
				'capabilities' => [
					Capabilities::ACCESS_APP,
					Capabilities::CREATE_OFFERS,
					Capabilities::CREATE_CLIENTS,
				],
				'wordpress' => self::MEDIA,
			],
		];
	}

	public static function syncIfOutdated(): void
	{
		if ((int) get_option(self::VERSION_OPTION, 0) < self::VERSION) {
			self::sync();
		}
	}

	/**
	 * Idempotentnie tworzy role i ustawia capabilities `gst_*` zgodnie z definicjami.
	 */
	public static function sync(): void
	{
		foreach (self::definitions() as $key => $definition) {
			$role = get_role($key) ?? add_role($key, $definition['label'], ['read' => true]);

			if (! $role) {
				continue;
			}

			foreach (Capabilities::all() as $capability) {
				if (in_array($capability, $definition['capabilities'], true)) {
					$role->add_cap($capability);
				} elseif ($role->has_cap($capability)) {
					$role->remove_cap($capability);
				}
			}

			foreach ($definition['wordpress'] ?? [] as $capability) {
				$role->add_cap($capability);
			}
		}

		update_option(self::VERSION_OPTION, self::VERSION, false);
	}

	/**
	 * Nazwa roli aplikacji widoczna w interfejsie.
	 */
	public static function labelFor(WP_User $user): string
	{
		foreach (self::definitions() as $key => $definition) {
			if (in_array($key, $user->roles, true)) {
				return $definition['label'];
			}
		}

		return 'Użytkownik';
	}
}
