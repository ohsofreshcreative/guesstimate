<?php

namespace App\Guesstimate\Http\Controllers;

use App\Guesstimate\Auth\Roles;
use App\Guesstimate\Document\Contacts;
use App\Guesstimate\Support\Input;
use Illuminate\Http\Request;
use WP_User;

/**
 * Przypisywanie ról aplikacji użytkownikom WordPressa (tylko administrator).
 * Konta zakłada się w WordPressie; tu decydujemy tylko o dostępie do Guesstimate.
 */
class UserController extends Controller
{
	public const APP_ROLES = [
		'' => 'Brak dostępu',
		Roles::EMPLOYEE => 'Pracownik',
		Roles::MANAGER => 'Manager',
	];

	public function index()
	{
		return view('guesstimate.settings.users', $this->data());
	}

	public function update(Request $request, int $user)
	{
		$wpUser = get_user_by('id', $user);
		abort_unless($wpUser instanceof WP_User, 404);

		if (in_array(Roles::ADMINISTRATOR, $wpUser->roles, true)) {
			return $this->redirectWith(route('gst.users.index'), 'Rolą administratora zarządza się w WordPressie.', 'error');
		}

		$role = (string) $request->input('role');

		if (! array_key_exists($role, self::APP_ROLES)) {
			return $this->invalid('guesstimate.settings.users', $this->data(), ['role_' . $user => 'Wybierz rolę z listy.']);
		}

		$wpUser->remove_role(Roles::MANAGER);
		$wpUser->remove_role(Roles::EMPLOYEE);

		if ($role !== '') {
			$wpUser->add_role($role);
		}

		if (! $wpUser->roles) {
			$wpUser->add_role('subscriber');
		}

		return $this->redirectWith(route('gst.users.index'), sprintf('Zmieniono rolę użytkownika %s na: %s.', $wpUser->display_name, self::APP_ROLES[$role]));
	}

	/**
	 * Stanowisko i telefon pokazywane na stronie „Kontakt” w ofertach (imię i e-mail z konta WordPress).
	 */
	public function contact(Request $request, int $user)
	{
		$wpUser = get_user_by('id', $user);
		abort_unless($wpUser instanceof WP_User, 404);

		$role = mb_substr((string) Input::line($request->input('job_title')), 0, 120);
		$phone = mb_substr((string) Input::line($request->input('phone')), 0, 40);

		if ($phone !== '' && ! preg_match('/^[0-9+()\s\-]{6,40}$/', $phone)) {
			return $this->redirectWith(route('gst.users.index'), 'Podaj numer telefonu, np. +48 510 370 841.', 'error');
		}

		update_user_meta($wpUser->ID, Contacts::META_ROLE, $role);
		update_user_meta($wpUser->ID, Contacts::META_PHONE, $phone);

		return $this->redirectWith(route('gst.users.index'), sprintf('Zapisano dane kontaktowe: %s.', $wpUser->display_name));
	}

	private function data(): array
	{
		$users = array_map(function (WP_User $user) {
			$isAdmin = in_array(Roles::ADMINISTRATOR, $user->roles, true);
			$role = $isAdmin ? Roles::ADMINISTRATOR : (in_array(Roles::MANAGER, $user->roles, true) ? Roles::MANAGER : (in_array(Roles::EMPLOYEE, $user->roles, true) ? Roles::EMPLOYEE : ''));

			return [
				'id' => $user->ID,
				'name' => $user->display_name ?: $user->user_login,
				'email' => $user->user_email,
				'is_admin' => $isAdmin,
				'role' => $role,
				'is_current' => $user->ID === get_current_user_id(),
				'job_title' => (string) get_user_meta($user->ID, Contacts::META_ROLE, true),
				'phone' => (string) get_user_meta($user->ID, Contacts::META_PHONE, true),
			];
		}, get_users(['orderby' => 'display_name', 'number' => 500]));

		return [
			'users' => $users,
			'roles' => self::APP_ROLES,
			'newUserUrl' => admin_url('user-new.php'),
			'section' => 'uzytkownicy',
			'sections' => SettingsController::SECTIONS,
		];
	}
}
