<?php

namespace App\Guesstimate\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * WordPress dodaje ukośniki do $_GET/$_POST (wp_magic_quotes), a Request Laravela czyta te dane.
 * Bez tego cudzysłowy i apostrofy zapisywałyby się jako \" i \', a JSON z edytora mapy strony byłby nieczytelny.
 */
class UnslashInput
{
	public function handle(Request $request, Closure $next): Response
	{
		$request->query->replace(wp_unslash($request->query->all()));
		$request->request->replace(wp_unslash($request->request->all()));

		return $next($request);
	}
}
