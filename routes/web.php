<?php

use App\Http\Controllers\Portal\AccountController;
use App\Http\Controllers\Portal\AuthController;
use App\Http\Controllers\Portal\LocaleController;
use App\Http\Controllers\Portal\OrderController;
use App\Http\Controllers\Portal\PasswordController;
use App\Http\Middleware\EnsureCustomerCanLogIn;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetPortalLocale;
use Illuminate\Support\Facades\Route;

// The root is reserved for the public website. Until it exists, send people to the portal.
Route::redirect('/', '/portal');

Route::prefix('portal')
    ->name('portal.')
    ->middleware([SetPortalLocale::class, HandleInertiaRequests::class])
    ->group(function () {
        Route::post('taal/{locale}', [LocaleController::class, 'update'])->name('locale');

        Route::middleware('guest:customer')->group(function () {
            Route::get('login', [AuthController::class, 'create'])->name('login');
            Route::post('login', [AuthController::class, 'store'])->middleware('throttle:20,1');
            Route::get('wachtwoord-vergeten', [PasswordController::class, 'request'])->name('password.request');
            Route::post('wachtwoord-vergeten', [PasswordController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
        });

        // Outside `guest`: someone already signed in may still follow an invitation link.
        Route::get('wachtwoord/{token}', [PasswordController::class, 'edit'])->name('password.reset');
        Route::post('wachtwoord', [PasswordController::class, 'update'])->middleware('throttle:6,1')->name('password.update');

        Route::middleware(['auth:customer', EnsureCustomerCanLogIn::class])->group(function () {
            Route::get('/', [OrderController::class, 'create'])->name('order');
            Route::post('bestellingen', [OrderController::class, 'store'])->middleware('throttle:10,1')->name('orders.store');
            Route::get('bestellingen', [OrderController::class, 'index'])->name('orders.index');
            Route::get('bestellingen/{order}', [OrderController::class, 'show'])->name('orders.show');
            Route::put('bestellingen/{order}', [OrderController::class, 'update'])->middleware('throttle:10,1')->name('orders.update');
            Route::post('bestellingen/{order}/annuleren', [OrderController::class, 'cancel'])->middleware('throttle:10,1')->name('orders.cancel');

            Route::get('account', [AccountController::class, 'edit'])->name('account');
            Route::put('account/wachtwoord', [AccountController::class, 'updatePassword'])->name('account.password');
            Route::put('account/voorkeuren', [AccountController::class, 'updatePreferences'])->name('account.preferences');

            Route::post('logout', [AuthController::class, 'destroy'])->name('logout');
        });
    });
