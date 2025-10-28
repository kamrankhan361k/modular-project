<?php

use Illuminate\Support\Facades\Route;
use Modules\Payment\Http\Controllers\PaymentController;
use Modules\Payment\Http\Controllers\DashboardController;

Route::middleware(['web', 'auth'])->group(function () {
    // Checkout routes
    Route::get('/checkout', [PaymentController::class, 'showCheckoutForm'])->name('checkout');
    Route::post('/payment/process', [PaymentController::class, 'processPayment'])->name('payment.process');
    Route::get('/payment/success/{gateway}', [PaymentController::class, 'paymentSuccess'])->name('payment.success');
    Route::get('/payment/cancel/{gateway}', [PaymentController::class, 'paymentCancel'])->name('payment.cancel');

    // Dashboard routes
    Route::prefix('admin/payments')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('payment.dashboard');
        Route::get('/statistics', [DashboardController::class, 'statistics'])->name('payment.statistics');
        Route::get('/{transaction}', [DashboardController::class, 'show'])->name('payment.dashboard.show');
    });
});

// Webhook routes (no auth required)
Route::prefix('webhook')->group(function () {
    Route::post('/{gateway}', [PaymentController::class, 'paymentWebhook'])->name('payment.webhook');
});
