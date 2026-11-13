<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CancellationController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscrepancyController;
use App\Http\Controllers\NotificationStateController;
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
    Route::get('/powiadomienia/stan', NotificationStateController::class)->name('notifications.state');
    Route::get('/do-wyjasnienia', [DiscrepancyController::class, 'index'])->name('discrepancies.index');
    Route::post('/do-wyjasnienia/wyjasnione', [DiscrepancyController::class, 'dismiss'])->name('discrepancies.dismiss');
    Route::delete('/do-wyjasnienia/wyjasnione/{dismissal}', [DiscrepancyController::class, 'restore'])->name('discrepancies.restore');
    Route::get('/zamowienia', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/zamowienia/{order}', [OrderController::class, 'show'])->name('orders.show');

    // Anulacje: każdy zalogowany zakłada i edytuje, usuwa tylko administrator.
    Route::resource('anulacje', CancellationController::class)->except(['destroy'])
        ->parameters(['anulacje' => 'cancellation'])->names('cancellations');

    // Słowniki: podgląd dla wszystkich zalogowanych, zmiany tylko dla administratora.
    Route::get('/spolki', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('/kanaly', [SalesChannelController::class, 'index'])->name('channels.index');
    Route::get('/statusy', [OrderStatusController::class, 'index'])->name('statuses.index');

    Route::middleware('role:admin')->group(function () {
        Route::delete('/anulacje/{cancellation}', [CancellationController::class, 'destroy'])->name('cancellations.destroy');
        Route::resource('spolki', CompanyController::class)->except(['index', 'show'])
            ->parameters(['spolki' => 'company'])->names('companies');
        Route::resource('kanaly', SalesChannelController::class)->except(['index', 'show'])
            ->parameters(['kanaly' => 'channel'])->names('channels');
        Route::resource('statusy', OrderStatusController::class)->except(['index', 'show'])
            ->parameters(['statusy' => 'status'])->names('statuses');
    });
});
