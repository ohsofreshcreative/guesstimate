<?php

namespace App\Guesstimate\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprawdza capabilities na poziomie trasy, np. ->middleware('gst.can:gst_manage_settings').
 * Użytkownik musi mieć wszystkie wymienione capabilities.
 */
class Authorize
{
	public function handle(Request $request, Closure $next, string ...$capabilities): Response
	{
		foreach ($capabilities as $capability) {
			if (! current_user_can($capability)) {
				abort(403, 'Nie masz uprawnień do tej części aplikacji.');
			}
		}

		return $next($request);
	}
}
