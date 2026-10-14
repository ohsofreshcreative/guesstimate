<?php

namespace App\View\Composers;

use App\Guesstimate\Auth\AppLogin;
use App\Guesstimate\Auth\Roles;
use App\Guesstimate\Http\Middleware\VerifyNonce;
use App\Guesstimate\Support\Flash;
use App\Guesstimate\Support\Navigation;
use App\Guesstimate\Support\SchemaStatus;
use Roots\Acorn\View\Composer;

class GuesstimateLayout extends Composer
{
	/**
	 * @var array<int, string>
	 */
	protected static $views = [
		'guesstimate.layouts.app',
		'guesstimate.layouts.editor',
	];

	public function with(): array
	{
		$user = wp_get_current_user();
		$name = $user->exists() ? ($user->display_name ?: $user->user_login) : '';

		return [
			'navigation' => Navigation::build(
				fn (string $capability) => current_user_can($capability),
				fn (string $route) => route($route),
				request()->route()?->getName(),
			),
			'currentUser' => [
				'name' => $name,
				'initials' => $this->initials($name),
				'role' => $user->exists() ? Roles::labelFor($user) : '',
			],
			'profileUrl' => admin_url('profile.php'),
			'logoutUrl' => AppLogin::url('logout', ['_wpnonce' => wp_create_nonce('log-out')]),
			'gstNonce' => wp_create_nonce(VerifyNonce::ACTION),
			'flash' => Flash::pull(),
			'pendingMigrations' => current_user_can('manage_options') ? SchemaStatus::pending() : [],
		];
	}

	private function initials(string $name): string
	{
		$parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
		$letters = array_map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)), array_slice($parts, 0, 2));

		return implode('', $letters);
	}
}
