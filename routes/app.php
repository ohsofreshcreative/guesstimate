<?php

/*
|--------------------------------------------------------------------------
| Guesstimate — trasy aplikacji /app/
|--------------------------------------------------------------------------
|
| Ładowane w functions.php w grupie middleware `gst` (logowanie WP, brak cache,
| nonce przy zapisie). Uprawnienia do modułów: middleware `gst.can:<capability>`.
| Uprawnienia do konkretnych ofert sprawdza OfferPolicy w kontrolerach.
|
*/

use App\Guesstimate\Auth\Capabilities;
use App\Guesstimate\Http\Controllers\AiController;
use App\Guesstimate\Http\Controllers\ClickUpController;
use App\Guesstimate\Http\Controllers\ClientController;
use App\Guesstimate\Http\Controllers\ClientLookupController;
use App\Guesstimate\Http\Controllers\DashboardController;
use App\Guesstimate\Http\Controllers\DocumentController;
use App\Guesstimate\Http\Controllers\DocumentLibraryController;
use App\Guesstimate\Http\Controllers\FeatureController;
use App\Guesstimate\Http\Controllers\OfferController;
use App\Guesstimate\Http\Controllers\PublicationController;
use App\Guesstimate\Http\Controllers\SettingsController;
use App\Guesstimate\Http\Controllers\TrashController;
use App\Guesstimate\Http\Controllers\UserController;
use App\Guesstimate\Http\Controllers\WizardController;
use Illuminate\Support\Facades\Route;

Route::prefix('app')->name('gst.')->group(function () {
	Route::get('/', DashboardController::class)->name('dashboard');

	/*--- OFERTY ---*/
	Route::get('oferty', [OfferController::class, 'index'])->name('offers.index');
	Route::get('oferty/nowa', [WizardController::class, 'create'])->name('offers.create');
	Route::get('oferty/kosz', [TrashController::class, 'index'])->name('offers.trash');
	Route::post('oferty/kosz/{offer}/przywroc', [TrashController::class, 'restore'])->whereNumber('offer')->name('offers.trash.restore');
	Route::post('oferty/kosz/{offer}/usun', [TrashController::class, 'destroy'])->whereNumber('offer')->name('offers.trash.destroy');
	Route::post('oferty/{offer}/kosz', [TrashController::class, 'trash'])->whereNumber('offer')->name('offers.trash.move');
	Route::post('oferty', [WizardController::class, 'store'])->name('offers.store');
	Route::get('oferty/{offer}', [OfferController::class, 'show'])->whereNumber('offer')->name('offers.show');
	Route::post('oferty/{offer}/status', [OfferController::class, 'status'])->whereNumber('offer')->name('offers.status');
	Route::post('oferty/{offer}/duplikuj', [OfferController::class, 'duplicate'])->whereNumber('offer')->name('offers.duplicate');
	Route::post('oferty/{offer}/nowa-wersja', [OfferController::class, 'newVersion'])->whereNumber('offer')->name('offers.version');
	Route::get('oferty/{offer}/kreator/{step}', [WizardController::class, 'show'])->whereNumber(['offer', 'step'])->name('offers.wizard');
	Route::post('oferty/{offer}/kreator/{step}', [WizardController::class, 'save'])->whereNumber(['offer', 'step'])->name('offers.wizard.save');
	Route::post('oferty/{offer}/podglad', [WizardController::class, 'preview'])->whereNumber('offer')->name('offers.preview');
	Route::post('oferty/{offer}/kreator/{step}/wycena', [WizardController::class, 'live'])->whereNumber(['offer', 'step'])->name('offers.wizard.live');

	/*--- PUBLIKACJA ONLINE ---*/
	Route::get('oferty/{offer}/podglad-online', [PublicationController::class, 'preview'])->whereNumber('offer')->name('offers.public-preview');

	/*--- EDYTOR DOKUMENTU (podgląd LIVE bez zapisu, zapis świadomy) ---*/
	Route::get('oferty/{offer}/dokument', [DocumentController::class, 'edit'])->whereNumber('offer')->name('offers.document');
	Route::post('oferty/{offer}/dokument', [DocumentController::class, 'save'])->whereNumber('offer')->name('offers.document.save');
	Route::get('oferty/{offer}/dokument/ramka', [DocumentController::class, 'frame'])->whereNumber('offer')->name('offers.document.frame');
	Route::post('oferty/{offer}/dokument/podglad', [DocumentController::class, 'preview'])->whereNumber('offer')->name('offers.document.preview');
	Route::post('oferty/{offer}/dokument/dopasowanie', [DocumentController::class, 'fit'])->whereNumber('offer')->name('offers.document.fit');
	Route::get('media', [DocumentController::class, 'media'])->name('media.index');
	Route::post('media', [DocumentController::class, 'upload'])->name('media.store');
	Route::post('oferty/{offer}/publikuj', [PublicationController::class, 'publish'])->whereNumber('offer')->name('offers.publish');
	Route::post('oferty/{offer}/wycofaj', [PublicationController::class, 'withdraw'])->whereNumber('offer')->name('offers.withdraw');
	Route::post('oferty/{offer}/przywroc-publikacje', [PublicationController::class, 'restore'])->whereNumber('offer')->name('offers.restore');

	/*--- ASYSTENT AI (tylko propozycje, bez zapisu) ---*/
	Route::post('oferty/{offer}/ai/estymacja', [AiController::class, 'estimate'])->whereNumber('offer')->name('ai.estimate');
	Route::post('oferty/{offer}/ai/tresci', [AiController::class, 'content'])->whereNumber('offer')->name('ai.content');

	/*--- WYSZUKIWARKA KLIENTÓW W KREATORZE (lokalni + ClickUp) ---*/
	Route::get('klienci/szukaj', [ClientLookupController::class, 'search'])->name('clients.search');
	Route::post('klienci/clickup-import', [ClientLookupController::class, 'import'])->name('clients.clickup-import');

	/*--- MOJE INTEGRACJE: ClickUp OAuth 2.0 (połączenie, konfiguracja i indeks firm zalogowanego użytkownika) ---*/
	Route::middleware('gst.can:' . Capabilities::CREATE_OFFERS)->group(function () {
		Route::get('moje-integracje', [ClickUpController::class, 'index'])->name('integrations.index');
		Route::post('moje-integracje/clickup/polacz', [ClickUpController::class, 'connect'])->name('integrations.clickup.connect');
		Route::post('moje-integracje/clickup/rozlacz', [ClickUpController::class, 'disconnect'])->name('integrations.clickup.disconnect');
		Route::post('moje-integracje/clickup/sprawdz', [ClickUpController::class, 'test'])->name('integrations.clickup.test');
		Route::post('moje-integracje/clickup/opcje', [ClickUpController::class, 'options'])->name('integrations.clickup.options');
		Route::post('moje-integracje/clickup/konfiguracja', [ClickUpController::class, 'save'])->name('integrations.clickup.save');
		Route::post('moje-integracje/clickup/synchronizuj', [ClickUpController::class, 'startSync'])->name('integrations.clickup.sync');
		Route::post('moje-integracje/clickup/synchronizacja', [ClickUpController::class, 'syncStatus'])->name('integrations.clickup.status');
		// Adres powrotu z ClickUp (Redirect URI aplikacji OAuth). GET bez nonce — chroniony jednorazowym `state`.
		Route::get('integrations/clickup/callback', [ClickUpController::class, 'callback'])->name('integrations.clickup.callback');
	});

	/*--- KLIENCI ---*/
	Route::middleware('gst.can:' . Capabilities::MANAGE_CLIENTS)->group(function () {
		Route::get('klienci', [ClientController::class, 'index'])->name('clients.index');
		Route::post('klienci', [ClientController::class, 'store'])->name('clients.store');
		Route::get('klienci/{client}', [ClientController::class, 'show'])->whereNumber('client')->name('clients.show');
		Route::post('klienci/{client}', [ClientController::class, 'update'])->whereNumber('client')->name('clients.update');
		Route::post('klienci/{client}/usun', [ClientController::class, 'destroy'])->whereNumber('client')->name('clients.destroy');
		Route::post('klienci/{client}/przywroc', [ClientController::class, 'restore'])->whereNumber('client')->name('clients.restore');
	});

	/*--- BIBLIOTEKA FUNKCJONALNOŚCI ---*/
	Route::middleware('gst.can:' . Capabilities::MANAGE_LIBRARY)->group(function () {
		Route::get('funkcjonalnosci', [FeatureController::class, 'index'])->name('features.index');
		Route::post('funkcjonalnosci', [FeatureController::class, 'store'])->name('features.store');
		Route::post('funkcjonalnosci/kategorie', [FeatureController::class, 'storeCategory'])->name('features.categories.store');
		Route::post('funkcjonalnosci/kategorie/{category}', [FeatureController::class, 'updateCategory'])->whereNumber('category')->name('features.categories.update');
		Route::post('funkcjonalnosci/{feature}', [FeatureController::class, 'update'])->whereNumber('feature')->name('features.update');
	});

	/*--- USTAWIENIA ---*/
	Route::middleware('gst.can:' . Capabilities::MANAGE_SETTINGS)->group(function () {
		Route::get('ustawienia/uzytkownicy', [UserController::class, 'index'])->middleware('gst.can:' . Capabilities::MANAGE_USERS)->name('users.index');
		Route::post('ustawienia/uzytkownicy/{user}/kontakt', [UserController::class, 'contact'])->whereNumber('user')->middleware('gst.can:' . Capabilities::MANAGE_USERS)->name('users.contact');
		Route::post('ustawienia/uzytkownicy/{user}', [UserController::class, 'update'])->whereNumber('user')->middleware('gst.can:' . Capabilities::MANAGE_USERS)->name('users.update');
		Route::post('ustawienia/typy-podstron', [SettingsController::class, 'storePageType'])->name('settings.page-types.store');
		Route::post('ustawienia/typy-podstron/{pageType}', [SettingsController::class, 'updatePageType'])->whereNumber('pageType')->name('settings.page-types.update');
		Route::post('ustawienia/tla', [DocumentLibraryController::class, 'storeBackground'])->name('settings.backgrounds.store');
		Route::post('ustawienia/tla/{background}', [DocumentLibraryController::class, 'updateBackground'])->whereNumber('background')->name('settings.backgrounds.update');
		Route::post('ustawienia/tla/{background}/usun', [DocumentLibraryController::class, 'destroyBackground'])->whereNumber('background')->name('settings.backgrounds.destroy');
		Route::post('ustawienia/realizacje', [DocumentLibraryController::class, 'storeCase'])->name('settings.cases.store');
		Route::post('ustawienia/realizacje/{caseStudy}', [DocumentLibraryController::class, 'updateCase'])->whereNumber('caseStudy')->name('settings.cases.update');
		Route::get('ustawienia/{section?}', [SettingsController::class, 'index'])->name('settings.index');
		Route::post('ustawienia/{section}', [SettingsController::class, 'update'])->name('settings.update');
	});
});
