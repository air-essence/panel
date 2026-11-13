<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderStatusController;
use App\Http\Controllers\SalesChannelController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/logowanie', [LoginController::class, 'show'])->name('login');
    Route::post('/logowanie', [LoginController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/wyloguj', [LoginController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/zamowienia', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/zamowienia/{order}', [OrderController::class, 'show'])->name('orders.show');

    // Słowniki: podgląd dla wszystkich zalogowanych, zmiany tylko dla administratora.
    Route::get('/spolki', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('/kanaly', [SalesChannelController::class, 'index'])->name('channels.index');
    Route::get('/statusy', [OrderStatusController::class, 'index'])->name('statuses.index');

    Route::middleware('role:admin')->group(function () {
        Route::resource('spolki', CompanyController::class)->except(['index', 'show'])
            ->parameters(['spolki' => 'company'])->names('companies');
        Route::resource('kanaly', SalesChannelController::class)->except(['index', 'show'])
            ->parameters(['kanaly' => 'channel'])->names('channels');
        Route::resource('statusy', OrderStatusController::class)->except(['index', 'show'])
            ->parameters(['statusy' => 'status'])->names('statuses');
    });
});
