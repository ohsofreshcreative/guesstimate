<?php

namespace App\Guesstimate\Http\Middleware;

use App\Guesstimate\Support\SchemaStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gdy tabele aplikacji jeszcze nie istnieją, pokazuje instrukcję zamiast błędu bazy danych.
 */
class EnsureSchema
{
	public function handle(Request $request, Closure $next): Response
	{
		// Moduł umów ma własne tabele; reszta aplikacji działa bez nich, a tu byłby błąd bazy zamiast instrukcji.
		if (! Schema::hasTable('gst_offer_versions') || ($request->routeIs('gst.contract-templates.*', 'gst.contracts.*') && ! Schema::hasTable('gst_contract_templates'))) {
			return response(view('guesstimate.setup', ['pending' => SchemaStatus::pending()])->render(), 503);
		}

		return $next($request);
	}
}
