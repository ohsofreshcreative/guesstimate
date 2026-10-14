<?php

namespace App\Guesstimate\Support;

/**
 * Jednorazowe komunikaty po przekierowaniu (np. „Zapisano klienta”).
 * Aplikacja nie korzysta z sesji Laravela, więc komunikat trzymamy w transiencie użytkownika.
 */
final class Flash
{
	private const TTL = 120;

	/**
	 * @param  array{url: string, label: string}|null  $action  opcjonalny przycisk (POST z nonce), np. „Cofnij”
	 */
	public static function success(string $message, ?array $action = null): void
	{
		self::put('success', $message, $action);
	}

	public static function error(string $message): void
	{
		self::put('error', $message);
	}

	/**
	 * @return list<array{type: string, message: string, action?: array|null}>
	 */
	public static function pull(): array
	{
		$key = self::key();

		if ($key === null) {
			return [];
		}

		$messages = get_transient($key) ?: [];
		delete_transient($key);

		return $messages;
	}

	private static function put(string $type, string $message, ?array $action = null): void
	{
		$key = self::key();

		if ($key === null) {
			return;
		}

		$messages = get_transient($key) ?: [];
		$messages[] = ['type' => $type, 'message' => $message, 'action' => $action];
		set_transient($key, $messages, self::TTL);
	}

	private static function key(): ?string
	{
		$userId = get_current_user_id();

		return $userId ? 'gst_flash_' . $userId : null;
	}
}
