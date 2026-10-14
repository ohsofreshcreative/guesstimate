<?php

namespace App\Guesstimate\Http\Controllers;

use App\Guesstimate\Auth\AppLogin;
use App\Guesstimate\Auth\LoginThrottle;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WP_Error;
use WP_User;

/**
 * Logowanie do Guesstimate na kontach WordPressa: /login, /logout, /forgot-password, /reset-password.
 *
 * Bezpieczeństwo: formularze z nonce (gdy strona była zbuforowana — żądanie z tej samej domeny), logowanie przez
 * wp_signon() (hasła i ciasteczka WordPressa, „Nie wylogowuj mnie” = 14 dni), limit prób (LoginThrottle),
 * przekierowania tylko w obrębie domeny (AppLogin::safeRedirect), jeden komunikat błędu bez wskazania, czy konto
 * istnieje, reset hasła wyłącznie mechanizmem WordPressa (klucz, ważność, jednorazowość). Hasła nie są logowane.
 */
class AuthController extends Controller
{
	private const GENERIC_INVALID = 'Nieprawidłowy e-mail / login lub hasło.';

	public function showLogin(Request $request)
	{
		$redirect = AppLogin::safeRedirect((string) $request->query('redirect_to', ''));
		$reauth = $request->boolean('reauth');

		if (is_user_logged_in() && ! $reauth) {
			return redirect()->away($redirect);
		}

		if ($reauth) {
			wp_clear_auth_cookie();
		}

		$notice = match (true) {
			$request->boolean('loggedout') => 'Wylogowano. Do zobaczenia!',
			$request->boolean('reset') => 'Hasło zostało zmienione. Zaloguj się nowym hasłem.',
			$reauth => 'Sesja wygasła — zaloguj się ponownie.',
			default => null,
		};

		return $this->page('login', ['notice' => $notice, 'error' => null, 'login' => '', 'redirect' => $redirect]);
	}

	public function login(Request $request)
	{
		$redirect = AppLogin::safeRedirect((string) $request->input('redirect_to', ''));
		$login = trim(sanitize_user((string) $request->input('log', ''), false));
		$password = (string) $request->input('pwd', '');

		$error = match (true) {
			! $this->trusted($request) => 'Formularz wygasł — odśwież stronę i spróbuj ponownie.',
			$login === '' || $password === '' => 'Podaj e-mail lub login oraz hasło.',
			default => null,
		};

		if ($error === null) {
			$user = wp_signon(['user_login' => $login, 'user_password' => $password, 'remember' => $request->boolean('rememberme')], is_ssl());

			if ($user instanceof WP_User) {
				wp_set_current_user($user->ID);
				$target = apply_filters('login_redirect', $redirect, $request->filled('redirect_to') ? $redirect : '', $user);

				return redirect()->away(AppLogin::safeRedirect((string) $target));
			}

			$error = $this->loginError($user);
		}

		return $this->page('login', ['notice' => null, 'error' => $error, 'login' => $login, 'redirect' => $redirect], str_starts_with($error, 'Za dużo') ? 429 : 422);
	}

	/**
	 * Wylogowanie: link z nonce WordPressa (GET) albo formularz potwierdzenia (POST z nonce).
	 */
	public function logout(Request $request)
	{
		if (! is_user_logged_in()) {
			return redirect()->away(AppLogin::url('login'));
		}

		$nonce = (string) $request->input('_wpnonce', '');

		if (! wp_verify_nonce($nonce, 'log-out')) {
			return $this->page('logout', []);
		}

		wp_logout();

		return redirect()->away(AppLogin::url('login', ['loggedout' => '1']));
	}

	public function showForgot()
	{
		return $this->page('forgot', ['sent' => false, 'error' => null, 'login' => '']);
	}

	public function forgot(Request $request)
	{
		$login = trim(sanitize_text_field((string) $request->input('user_login', '')));

		if (! $this->trusted($request)) {
			return $this->page('forgot', ['sent' => false, 'error' => 'Formularz wygasł — odśwież stronę i spróbuj ponownie.', 'login' => $login], 422);
		}

		if ($login === '') {
			return $this->page('forgot', ['sent' => false, 'error' => 'Podaj adres e-mail lub login.', 'login' => $login], 422);
		}

		if (! LoginThrottle::allowReset(LoginThrottle::clientIp())) {
			return $this->page('forgot', ['sent' => false, 'error' => 'Za dużo próśb o zmianę hasła. Spróbuj ponownie za kilkanaście minut.', 'login' => $login], 429);
		}

		// Ten sam komunikat niezależnie od tego, czy konto istnieje (brak wyliczania kont).
		retrieve_password($login);

		return $this->page('forgot', ['sent' => true, 'error' => null, 'login' => '']);
	}

	public function showReset(Request $request)
	{
		[$key, $login] = $this->resetParams($request);
		$user = check_password_reset_key($key, $login);

		if ($user instanceof WP_Error) {
			return $this->page('reset', ['invalid' => $this->resetError($user), 'key' => '', 'login' => '', 'errors' => []], 410);
		}

		return $this->page('reset', ['invalid' => null, 'key' => $key, 'login' => $login, 'errors' => []]);
	}

	public function reset(Request $request)
	{
		[$key, $login] = $this->resetParams($request);
		$user = check_password_reset_key($key, $login);

		if ($user instanceof WP_Error) {
			return $this->page('reset', ['invalid' => $this->resetError($user), 'key' => '', 'login' => '', 'errors' => []], 410);
		}

		$pass = (string) $request->input('pass1', '');
		$confirm = (string) $request->input('pass2', '');
		$errors = [];

		if (! $this->trusted($request)) {
			$errors['form'] = 'Formularz wygasł — odśwież stronę i spróbuj ponownie.';
		} elseif (mb_strlen($pass) < 10) {
			$errors['pass1'] = 'Hasło musi mieć co najmniej 10 znaków.';
		} elseif (str_contains($pass, '\\')) {
			$errors['pass1'] = 'Hasło nie może zawierać znaku „\\”.';
		} elseif ($pass !== $confirm) {
			$errors['pass2'] = 'Hasła nie są takie same.';
		}

		if ($errors) {
			return $this->page('reset', ['invalid' => null, 'key' => $key, 'login' => $login, 'errors' => $errors], 422);
		}

		reset_password($user, $pass);

		return redirect()->away(AppLogin::url('login', ['reset' => '1']));
	}

	/**
	 * @param  array<string, mixed>  $data
	 */
	private function page(string $view, array $data, int $status = 200)
	{
		$response = response()->view('guesstimate.auth.' . $view, $data + [
			'nonce' => wp_create_nonce(AppLogin::NONCE),
			'urls' => [
				'login' => AppLogin::url('login'),
				'forgot' => AppLogin::url('forgot-password'),
				'reset' => AppLogin::url('reset-password'),
				'logout' => AppLogin::url('logout'),
			],
		], $status);

		// Klucz resetu w adresie nie może wyciec w nagłówku Referer (np. do Google Fonts).
		$response->headers->set('Referrer-Policy', 'no-referrer');
		$response->headers->set('X-Frame-Options', 'DENY');

		return $response;
	}

	/**
	 * Nonce formularza; gdy strona logowania była zbuforowana przez cache hostingu (stary nonce) — wystarczy
	 * żądanie z tej samej domeny (Origin / Referer).
	 */
	private function trusted(Request $request): bool
	{
		if (wp_verify_nonce((string) $request->input('_gst_login', ''), AppLogin::NONCE)) {
			return true;
		}

		$home = wp_parse_url(home_url(), PHP_URL_HOST);

		foreach (['Origin', 'Referer'] as $header) {
			if ($value = $request->headers->get($header)) {
				return wp_parse_url($value, PHP_URL_HOST) === $home;
			}
		}

		return false;
	}

	private function loginError($result): string
	{
		$code = $result instanceof WP_Error ? (string) $result->get_error_code() : '';

		if ($code === LoginThrottle::ERROR) {
			return (string) $result->get_error_message();
		}

		// Standardowe błędy: jeden komunikat (bez ujawniania, czy konto istnieje). Inne (np. wtyczka 2FA) — jej komunikat.
		if ($code === '' || in_array($code, ['invalid_username', 'invalid_email', 'incorrect_password', 'empty_username', 'empty_password', 'authentication_failed'], true)) {
			return self::GENERIC_INVALID;
		}

		$text = trim(wp_strip_all_tags((string) $result->get_error_message()));

		return $text !== '' ? mb_substr($text, 0, 300) : self::GENERIC_INVALID;
	}

	/**
	 * @return array{0: string, 1: string}
	 */
	private function resetParams(Request $request): array
	{
		$key = (string) $request->input('key', '');
		$login = (string) $request->input('login', '');

		return [preg_match('/^[A-Za-z0-9]{1,64}$/', $key) ? $key : '', mb_substr($login, 0, 60)];
	}

	private function resetError(WP_Error $error): string
	{
		return $error->get_error_code() === 'expired_key'
			? 'Link do ustawienia hasła wygasł. Poproś o nowy.'
			: 'Link do ustawienia hasła jest nieprawidłowy albo został już użyty. Poproś o nowy.';
	}
}
