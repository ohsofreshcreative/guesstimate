<?php

/*
|--------------------------------------------------------------------------
| Guesstimate — publiczna oferta online
|--------------------------------------------------------------------------
|
| Bez logowania: dostęp wyłącznie przez losowy token w adresie. Grupa `gst.public`
| (bez cache, noindex). Sygnał wyświetlenia przyjmuje tylko POST bez danych.
|
*/

use App\Guesstimate\Http\Controllers\PublicOfferController;
use Illuminate\Support\Facades\Route;

Route::name('gst.public.')->group(function () {
	Route::get('offer/{token}', [PublicOfferController::class, 'show'])->name('offer');
	Route::post('offer/{token}/view', [PublicOfferController::class, 'view'])->name('offer.view');
});
