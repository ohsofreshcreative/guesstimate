<?php

namespace App\Guesstimate\Support;

use App\Guesstimate\Settings\SettingsRepository;

/**
 * Sekrety integracji (klucz OpenAI, token ClickUp). Przechowywane tylko po stronie serwera, zaszyfrowane
 * (libsodium, klucz z soli WordPressa). Nigdy nie trafiają do HTML, JS ani logów — widoki dostają
 * wyłącznie informację, czy sekret jest ustawiony, i jego ostatnie 4 znaki.
 */
final class Secrets
{
	private const SETTING = 'secrets';

	public function __construct(private SettingsRepository $settings)
	{
	}

	public function get(string $name): ?string
	{
		$stored = $this->stored()[$name] ?? null;

		if (! is_array($stored) || empty($stored['value'])) {
			return null;
		}

		$raw = base64_decode($stored['value'], true);

		if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
			return null;
		}

		$plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), self::key());

		return $plain === false ? null : $plain;
	}

	public function set(string $name, ?string $value): void
	{
		$all = $this->stored();

		if ($value === null || $value === '') {
			unset($all[$name]);
		} else {
			$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
			$all[$name] = [
				'value' => base64_encode($nonce . sodium_crypto_secretbox($value, $nonce, self::key())),
				'hint' => mb_substr($value, -4),
				'updated_at' => current_time('mysql'),
			];
		}

		$this->settings->set(self::SETTING, $all);
	}

	/**
	 * Szyfrowanie wartości przechowywanej poza ustawieniami (np. token OAuth użytkownika w osobnej tabeli).
	 * $context (np. „clickup-user-12”) jest częścią zaszyfrowanej treści — szyfrogramu skopiowanego do
	 * innego rekordu nie da się odczytać.
	 */
	public function seal(string $value, string $context): string
	{
		$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

		return base64_encode($nonce . sodium_crypto_secretbox($context . "\n" . $value, $nonce, self::key()));
	}

	public function open(?string $sealed, string $context): ?string
	{
		$raw = $sealed ? base64_decode($sealed, true) : false;

		if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
			return null;
		}

		$plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), self::key());

		if ($plain === false || ! str_starts_with($plain, $context . "\n")) {
			return null;
		}

		return substr($plain, strlen($context) + 1);
	}

	/**
	 * Informacja dla widoku ustawień: czy ustawiony i maska („•••• abcd”). Bez wartości sekretu.
	 */
	public function describe(string $name): array
	{
		$stored = $this->stored()[$name] ?? null;
		$set = is_array($stored) && ! empty($stored['value']) && $this->get($name) !== null;

		return ['set' => $set, 'mask' => $set ? '•••• ' . ($stored['hint'] ?? '') : null];
	}

	private function stored(): array
	{
		return (array) ($this->settings->get(self::SETTING) ?? []);
	}

	private static function key(): string
	{
		return sodium_crypto_generichash('guesstimate-secrets|' . wp_salt('secure_auth'), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
	}
}
