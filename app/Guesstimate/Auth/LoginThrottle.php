<?php

namespace App\Guesstimate\Auth;

use WP_Error;
use WP_User;

/**
 * Ograniczenie prób logowania (ochrona przed zgadywaniem haseł) — działa dla każdego logowania WordPressa
 * (ekran /login, wp-login.php, XML-RPC), bo opiera się na filtrze `authenticate` i akcji `wp_login_failed`.
 *
 * Dwa liczniki w oknie 15 minut:
 * - para IP + login: 5 nieudanych prób → blokada pary (atakujący z innego IP nie zablokuje właściciela konta),
 * - samo IP: 20 nieudanych prób (dowolne loginy) → blokada adresu.
 * Udane logowanie zeruje licznik pary. Dane w transientach; klucze to skróty (bez loginów i haseł w bazie).
 */
final class LoginThrottle
{
	public const WINDOW = 15 * MINUTE_IN_SECONDS;
	public const MAX_PER_LOGIN = 5;
	public const MAX_PER_IP = 20;
	public const ERROR = 'gst_login_locked';

	public static function register(): void
	{
		add_filter('authenticate', [self::class, 'guard'], 99, 2);
		add_action('wp_login_failed', fn ($login) => self::fail(self::clientIp(), (string) $login));
		add_action('wp_login', fn ($login) => self::succeed(self::clientIp(), (string) $login));
	}

	/**
	 * @param  WP_User|WP_Error|null  $user
	 */
	public static function guard($user, $login)
	{
		$login = is_string($login) ? $login : '';

		if ($login === '') {
			return $user;
		}

		$wait = self::lockedFor(self::clientIp(), $login);

		if ($wait > 0) {
			return new WP_Error(self::ERROR, sprintf('Za dużo nieudanych prób logowania. Spróbuj ponownie za %d min.', max(1, (int) ceil($wait / 60))));
		}

		return $user;
	}

	public static function lockedFor(string $ip, string $login): int
	{
		$wait = 0;

		foreach ([[self::pairKey($ip, $login), self::MAX_PER_LOGIN], [self::ipKey($ip), self::MAX_PER_IP]] as [$key, $max]) {
			$state = self::state($key);

			if ($state['n'] >= $max) {
				$wait = max($wait, $state['start'] + self::WINDOW - time());
			}
		}

		return max(0, $wait);
	}

	public static function fail(string $ip, string $login): void
	{
		foreach ([self::pairKey($ip, $login), self::ipKey($ip)] as $key) {
			$state = self::state($key);
			$state['n']++;
			set_transient($key, $state, max(60, $state['start'] + self::WINDOW - time()));
		}
	}

	public static function succeed(string $ip, string $login): void
	{
		delete_transient(self::pairKey($ip, $login));
	}

	/**
	 * Limit próśb o reset hasła (IP): 5 w oknie 15 minut — bez ujawniania, czy konto istnieje.
	 */
	public static function allowReset(string $ip): bool
	{
		$key = 'gst_pwreset_ip_' . md5($ip);
		$state = self::state($key);

		if ($state['n'] >= 5) {
			return false;
		}

		$state['n']++;
		set_transient($key, $state, max(60, $state['start'] + self::WINDOW - time()));

		return true;
	}

	public static function clientIp(): string
	{
		$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

		return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
	}

	/**
	 * @return array{n: int, start: int}
	 */
	private static function state(string $key): array
	{
		$state = get_transient($key);

		if (! is_array($state) || ! isset($state['n'], $state['start']) || (int) $state['start'] + self::WINDOW <= time()) {
			return ['n' => 0, 'start' => time()];
		}

		return ['n' => (int) $state['n'], 'start' => (int) $state['start']];
	}

	private static function pairKey(string $ip, string $login): string
	{
		return 'gst_login_f_' . md5($ip . '|' . mb_strtolower(trim($login)));
	}

	private static function ipKey(string $ip): string
	{
		return 'gst_login_ip_' . md5($ip);
	}
}
