<?php

namespace App\Guesstimate\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ochrona CSRF dla operacji zapisu oparta na nonce WordPressa.
 *
 * Formularze wysyłają pole `_gst_nonce`, żądania fetch nagłówek `X-GST-Nonce`.
 */
class VerifyNonce
{
	public const ACTION = 'gst_app';
	public const FIELD = '_gst_nonce';
	public const HEADER = 'X-GST-Nonce';

	public function handle(Request $request, Closure $next): Response
	{
		if ($request->isMethodSafe()) {
			return $next($request);
		}

		$nonce = $request->header(self::HEADER) ?? $request->input(self::FIELD);

		if (! is_string($nonce) || ! wp_verify_nonce($nonce, self::ACTION)) {
			abort(419, 'Formularz wygasł. Odśwież stronę i spróbuj ponownie.');
		}

		return $next($request);
	}
}
