<?php

namespace App\Guesstimate\Http\Middleware;

use App\Guesstimate\Auth\AppLogin;
use App\Guesstimate\Auth\Capabilities;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wpuszcza do /app/ tylko zalogowanych użytkowników WordPressa z capability gst_access_app.
 */
class Authenticate
{
	public function handle(Request $request, Closure $next): Response
	{
		if (! is_user_logged_in()) {
			if ($request->expectsJson()) {
				abort(401, 'Sesja wygasła. Zaloguj się ponownie.');
			}

			return redirect()->away(AppLogin::url('login', ['redirect_to' => $request->fullUrl()]));
		}

		if (! current_user_can(Capabilities::ACCESS_APP)) {
			abort(403, 'To konto nie ma dostępu do aplikacji Guesstimate.');
		}

		return $next($request);
	}
}
