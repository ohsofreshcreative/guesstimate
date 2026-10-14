<?php

namespace App\Guesstimate\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wyklucza aplikację z cache przeglądarki, proxy i LiteSpeed Cache.
 * Bez tego zalogowany użytkownik mógłby dostać stronę zbuforowaną dla innej osoby.
 */
class PreventCaching
{
	public function handle(Request $request, Closure $next): Response
	{
		if (! defined('DONOTCACHEPAGE')) {
			define('DONOTCACHEPAGE', true);
		}

		do_action('litespeed_control_set_nocache', 'Guesstimate: aplikacja /app/');

		$response = $next($request);

		$response->headers->set('Cache-Control', 'no-store, private, max-age=0');
		$response->headers->set('X-LiteSpeed-Cache-Control', 'no-cache');
		$response->headers->set('X-Robots-Tag', 'noindex, nofollow');

		return $response;
	}
}
