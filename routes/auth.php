<?php

/*
|--------------------------------------------------------------------------
| Guesstimate — logowanie (konta WordPressa)
|--------------------------------------------------------------------------
|
| Grupa `gst.guest`: bez wymogu zalogowania, bez cache. CSRF: nonce formularzy (AuthController::trusted),
| wylogowanie przez link z nonce WordPressa albo formularz POST. Reset hasła — mechanizm WordPressa.
|
*/

use App\Guesstimate\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::name('gst.auth.')->group(function () {
	Route::get('login', [AuthController::class, 'showLogin'])->name('login');
	Route::post('login', [AuthController::class, 'login'])->name('login.submit');
	Route::match(['get', 'post'], 'logout', [AuthController::class, 'logout'])->name('logout');
	Route::get('forgot-password', [AuthController::class, 'showForgot'])->name('forgot');
	Route::post('forgot-password', [AuthController::class, 'forgot'])->name('forgot.submit');
	Route::get('reset-password', [AuthController::class, 'showReset'])->name('reset');
	Route::post('reset-password', [AuthController::class, 'reset'])->name('reset.submit');
});
