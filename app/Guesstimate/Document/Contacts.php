<?php

namespace App\Guesstimate\Document;

use App\Guesstimate\Auth\Capabilities;
use WP_User;

/**
 * Osoba kontaktowa oferty: profil pracownika (WordPress + pola Guesstimate) albo kontakt domyślny z ustawień.
 */
final class Contacts
{
	public const META_ROLE = 'gst_job_title';
	public const META_PHONE = 'gst_phone';

	/**
	 * Zespół z dostępem do aplikacji (do wyboru w ofercie).
	 *
	 * @return array<int, string>
	 */
	public static function team(): array
	{
		$out = [];

		foreach (get_users(['orderby' => 'display_name', 'number' => 300]) as $user) {
			if (user_can($user, Capabilities::ACCESS_APP)) {
				$out[$user->ID] = $user->display_name ?: $user->user_login;
			}
		}

		return $out;
	}

	public static function forUser(?int $userId): ?array
	{
		$user = $userId ? get_user_by('id', $userId) : null;

		if (! $user instanceof WP_User) {
			return null;
		}

		return [
			'name' => $user->display_name ?: $user->user_login,
			'role' => (string) get_user_meta($user->ID, self::META_ROLE, true),
			'phone' => (string) get_user_meta($user->ID, self::META_PHONE, true),
			'email' => $user->user_email,
		];
	}

	/**
	 * Kontakt do dokumentu: wybrana osoba, a gdy jej brak — kontakt domyślny z ustawień.
	 */
	public static function resolve(?int $userId, array $settings): array
	{
		$fallback = array_replace(['name' => '', 'role' => '', 'phone' => '', 'email' => ''], (array) ($settings['document']['contact'] ?? []));
		$contact = self::forUser($userId);

		if (! $contact) {
			return $fallback;
		}

		// Brakujące pola profilu uzupełniamy kontaktem domyślnym (np. ogólny telefon biura).
		foreach (['role', 'phone'] as $field) {
			if ($contact[$field] === '') {
				$contact[$field] = $fallback[$field];
			}
		}

		return $contact;
	}
}
