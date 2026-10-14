<?php

namespace App\Guesstimate\Auth;

use WP_User;

/**
 * Ekrany logowania Guesstimate (/login, /logout, /forgot-password, /reset-password) zamiast wp-login.php.
 *
 * - Wszystkie odnośniki „zaloguj się” WordPressa (auth_redirect, wp_login_url) prowadzą do /login.
 * - wp-login.php: zwykłe logowanie i „Nie pamiętasz hasła?” → nasze ekrany; link z e-maila (action=rp) → /reset-password.
 *   Pozostałe akcje (wylogowanie z nonce, okienko ponownego logowania w wp-admin, potwierdzenia) działają bez zmian.
 *   Awaryjnie: /wp-login.php?native=1 (np. przy problemie z motywem).
 * - Wylogowanie → /login?loggedout=1. E-mail resetu hasła prowadzi do /reset-password (klucz WordPressa).
 */
final class AppLogin
{
	public const NONCE = 'gst_login';

	public static function url(string $path = 'login', array $args = []): string
	{
		$args = array_filter($args, fn ($v) => $v !== null && $v !== '');

		return add_query_arg(array_map('rawurlencode', $args), home_url('/' . $path));
	}

	public static function register(): void
	{
		LoginThrottle::register();
		// Na `init`, nie wcześniej: Acorn przy starcie (after_setup_theme) pomija routing dla ścieżki wp_login_url() —
		// gdyby filtr działał już wtedy, Acorn nie obsłużyłby trasy /login.
		add_action('init', fn () => add_filter('login_url', fn ($url, $redirect = '', $reauth = false) => self::url('login', ['redirect_to' => $redirect, 'reauth' => $reauth ? '1' : null]), 20, 3), 1);
		add_filter('lostpassword_url', fn () => self::url('forgot-password'), 20);
		add_filter('logout_redirect', fn () => self::url('login', ['loggedout' => '1']), 20);
		add_action('login_init', [self::class, 'takeOverWpLogin'], 1);
		add_filter('retrieve_password_notification_email', [self::class, 'resetEmail'], 10, 4);
	}

	/**
	 * @param  array<string, mixed>  $query
	 */
	public static function wpLoginTarget(string $method, array $query): ?string
	{
		if (strtoupper($method) !== 'GET' || ! empty($query['native']) || ! empty($query['interim-login'])) {
			return null;
		}

		$action = (string) ($query['action'] ?? 'login');

		return match (true) {
			in_array($action, ['login', ''], true) && ! isset($query['checkemail']) => self::url('login', [
				'redirect_to' => isset($query['redirect_to']) ? (string) $query['redirect_to'] : null,
				'reauth' => ! empty($query['reauth']) ? '1' : null,
				'loggedout' => ! empty($query['loggedout']) ? '1' : null,
			]),
			in_array($action, ['lostpassword', 'retrievepassword'], true) => self::url('forgot-password'),
			in_array($action, ['rp', 'resetpass'], true) && isset($query['key'], $query['login']) => self::url('reset-password', ['key' => (string) $query['key'], 'login' => (string) $query['login']]),
			default => null,
		};
	}

	public static function takeOverWpLogin(): void
	{
		$target = self::wpLoginTarget((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), wp_unslash($_GET));

		if ($target !== null) {
			wp_safe_redirect($target);
			exit;
		}
	}

	/**
	 * Cel po zalogowaniu: adres z tej samej domeny (bez open redirect), nigdy ekrany logowania — domyślnie /app/.
	 */
	public static function safeRedirect(?string $url): string
	{
		$app = home_url('/app/');
		$url = trim((string) $url);

		if ($url === '') {
			return $app;
		}

		$valid = wp_validate_redirect($url, '');
		$path = (string) wp_parse_url($valid, PHP_URL_PATH);

		if ($valid === '' || str_contains($valid, 'wp-login.php') || preg_match('#/(login|logout|forgot-password|reset-password)/?$#', $path)) {
			return $app;
		}

		return $valid;
	}

	/**
	 * Treść e-maila resetu hasła: link do /reset-password (klucz i ważność — mechanizm WordPressa).
	 *
	 * @param  array{to: string, subject: string, message: string, headers: string}  $email
	 */
	public static function resetEmail(array $email, string $key, string $login, $user): array
	{
		$link = self::url('reset-password', ['key' => $key, 'login' => $login]);
		$hours = (int) round(apply_filters('password_reset_expiration', DAY_IN_SECONDS) / HOUR_IN_SECONDS);

		$email['subject'] = '[Guesstimate] Ustawienie nowego hasła';
		$email['message'] = "Dzień dobry,\r\n\r\n"
			. "ktoś (prawdopodobnie Ty) poprosił o zmianę hasła do Guesstimate dla konta: {$login}.\r\n\r\n"
			. "Aby ustawić nowe hasło, otwórz link (ważny {$hours} godz.):\r\n{$link}\r\n\r\n"
			. "Jeśli to nie Ty — zignoruj tę wiadomość, hasło się nie zmieni.\r\n\r\nGuesstimate · OhSoFresh Creative";

		return $email;
	}

	public static function isUser($user): bool
	{
		return $user instanceof WP_User;
	}
}
