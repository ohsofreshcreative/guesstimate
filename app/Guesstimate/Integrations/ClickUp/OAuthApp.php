<?php

namespace App\Guesstimate\Integrations\ClickUp;

/**
 * Aplikacja OAuth ClickUp (wspólna dla całej instalacji Guesstimate; tokeny są osobne dla każdego użytkownika).
 *
 * Konfiguracja poza repozytorium — stałe w wp-config.php albo zmienne środowiskowe o tych samych nazwach:
 *   GST_CLICKUP_CLIENT_ID, GST_CLICKUP_CLIENT_SECRET,
 *   GST_CLICKUP_REDIRECT_URI (opcjonalnie; domyślnie {home}/app/integrations/clickup/callback).
 * Client Secret nigdy nie trafia do bazy, HTML, JavaScriptu ani logów.
 *
 * Dokumentacja ClickUp (developer.clickup.com/docs/authentication): autoryzacja
 * https://app.clickup.com/api?client_id=…&redirect_uri=…&state=…, wymiana kodu
 * POST https://api.clickup.com/api/v2/oauth/token {client_id, client_secret, code} → {access_token},
 * nagłówek „Authorization: Bearer {token}”. Token „obecnie nie wygasa” i nie ma tokenu odświeżania —
 * błąd 401 (np. OAUTH_019/021/025/077 — cofnięty dostęp) oznacza konieczność ponownego połączenia.
 */
final class OAuthApp
{
	public const AUTHORIZE_URL = 'https://app.clickup.com/api';
	public const CALLBACK_PATH = '/app/integrations/clickup/callback';

	public static function clientId(): ?string
	{
		return self::value('GST_CLICKUP_CLIENT_ID');
	}

	public static function clientSecret(): ?string
	{
		return self::value('GST_CLICKUP_CLIENT_SECRET');
	}

	public static function redirectUri(): string
	{
		return self::value('GST_CLICKUP_REDIRECT_URI') ?? home_url(self::CALLBACK_PATH);
	}

	/**
	 * Stan konfiguracji (bez wartości sekretów).
	 *
	 * @return array{configured: bool, missing: list<string>, redirect_uri: string, https: bool, notes: list<string>}
	 */
	public static function configuration(): array
	{
		$missing = [];

		if (self::clientId() === null) {
			$missing[] = 'GST_CLICKUP_CLIENT_ID';
		}

		if (self::clientSecret() === null) {
			$missing[] = 'GST_CLICKUP_CLIENT_SECRET';
		}

		$redirect = self::redirectUri();
		$https = str_starts_with($redirect, 'https://');
		$notes = [];

		if (! $https) {
			$notes[] = 'Adres powrotu używa HTTP. ClickUp zapowiada, że adresy bez SSL mogą przestać być obsługiwane — na guesstimate.pl używaj HTTPS.';
		}

		if (wp_get_environment_type() === 'production' && ! $https) {
			$missing[] = 'HTTPS w adresie powrotu (środowisko produkcyjne)';
		}

		return ['configured' => $missing === [], 'missing' => $missing, 'redirect_uri' => $redirect, 'https' => $https, 'notes' => $notes];
	}

	public static function authorizeUrl(string $state): string
	{
		return self::AUTHORIZE_URL . '?' . http_build_query([
			'client_id' => self::clientId(),
			'redirect_uri' => self::redirectUri(),
			'state' => $state,
		]);
	}

	private static function value(string $name): ?string
	{
		$value = defined($name) ? constant($name) : getenv($name);
		$value = is_string($value) ? trim($value) : '';

		return $value !== '' ? $value : null;
	}
}
