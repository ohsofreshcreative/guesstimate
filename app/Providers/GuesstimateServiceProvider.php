<?php

namespace App\Providers;

use App\Guesstimate\Auth\AppLogin;
use App\Guesstimate\Auth\Capabilities;
use App\Guesstimate\Auth\Roles;
use App\Guesstimate\Document\MediaRefs;
use App\Guesstimate\Console\ClickUpCommand;
use App\Guesstimate\Integrations\ClickUp\ClickUpScheduler;
use App\Guesstimate\Integrations\ClickUp\ClickUpSync;
use App\Guesstimate\Offers\OfferPolicy;
use App\Guesstimate\Settings\SettingsRepository;
use Illuminate\Support\ServiceProvider;
use WP_User;

/**
 * Integracja aplikacji Guesstimate z WordPressem: role, logowanie, pasek admina, dostęp do wp-admin.
 * Trasy i middleware są zarejestrowane w functions.php (Application::configure()).
 */
class GuesstimateServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->useWordPressCharset();
		$this->app->singleton(SettingsRepository::class);
		$this->app->singleton(OfferPolicy::class);
	}

	public function boot(): void
	{
		add_action('init', [Roles::class, 'syncIfOutdated']);
		add_action('after_switch_theme', [Roles::class, 'sync']);

		add_filter('login_redirect', [$this, 'loginRedirect'], 10, 3);
		// Dedykowany ekran logowania (/login) zamiast wp-login.php, limit prób, reset hasła WordPressa.
		AppLogin::register();
		add_filter('show_admin_bar', fn ($show) => current_user_can('manage_options') ? $show : false);
		add_action('admin_init', [$this, 'keepTeamOutOfAdmin']);
		add_action('template_redirect', [$this, 'redirectFrontPageToApp']);

		// Załączniki używane w opublikowanych ofertach nie mogą zniknąć z biblioteki mediów.
		add_filter('pre_delete_attachment', [MediaRefs::class, 'guardDelete'], 10, 2);
		add_filter('attachment_fields_to_edit', [MediaRefs::class, 'describe'], 10, 2);

		// Synchronizacja firm z ClickUp: partie (WP-Cron per użytkownik) + harmonogram co 15 min
		// (systemowy cron → `wp guesstimate clickup sync-due`, rezerwowo WP-Cron co 5 min).
		ClickUpSync::register();
		ClickUpScheduler::register();

		if (defined('WP_CLI') && WP_CLI) {
			\WP_CLI::add_command('guesstimate clickup', ClickUpCommand::class);
		}
	}

	/**
	 * Acorn bierze kodowanie połączenia ze stałej DB_CHARSET, a sortowanie z $wpdb->collate.
	 * Gdy wp-config ma DB_CHARSET 'utf8', WordPress sam przechodzi na utf8mb4 (np. utf8mb4_unicode_520_ci)
	 * i MySQL odrzuca parę utf8 + utf8mb4_*. Używamy kodowania, którego faktycznie używa WordPress.
	 */
	private function useWordPressCharset(): void
	{
		global $wpdb;

		if (! isset($wpdb) || empty($wpdb->charset)) {
			return;
		}

		config([
			'database.connections.wordpress.charset' => $wpdb->charset,
			'database.connections.wordpress.collation' => $wpdb->collate ?: null,
		]);
	}

	/**
	 * Po zalogowaniu użytkownik aplikacji trafia do /app/, chyba że wracał na konkretny adres.
	 */
	public function loginRedirect(string $redirectTo, string $requested, $user): string
	{
		if (! $user instanceof WP_User || ! user_can($user, Capabilities::ACCESS_APP)) {
			return $redirectTo;
		}

		// Bez wskazanego adresu każdy trafia do aplikacji; domyślny adres wp-admin przekierowujemy
		// tylko dla zespołu — administrator, który wchodził do wp-admin, tam wraca.
		if ($requested === '' || ($requested === admin_url() && ! user_can($user, 'manage_options'))) {
			return home_url('/app/');
		}

		return $redirectTo;
	}

	/**
	 * Manager i pracownik pracują wyłącznie w /app/. Profil (zmiana hasła) zostaje dostępny.
	 */
	public function keepTeamOutOfAdmin(): void
	{
		if (wp_doing_ajax() || wp_doing_cron() || current_user_can('manage_options')) {
			return;
		}

		if (! current_user_can(Capabilities::ACCESS_APP)) {
			return;
		}

		global $pagenow;

		if (in_array($pagenow, ['profile.php', 'admin-post.php'], true)) {
			return;
		}

		wp_safe_redirect(home_url('/app/'));
		exit;
	}

	/**
	 * Instalacja służy wyłącznie aplikacji, więc strona główna prowadzi do /app/.
	 */
	public function redirectFrontPageToApp(): void
	{
		if (is_front_page()) {
			wp_safe_redirect(home_url('/app/'));
			exit;
		}
	}
}
