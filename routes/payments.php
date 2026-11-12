<?php

use App\Http\Controllers\Payments\ChapaWebhookController;
use App\Http\Controllers\Payments\PaymentReturnController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

// Chapa calls this itself, so there is no CSRF token: the signature is the protection (PAY-05).
Route::post('/webhooks/chapa', ChapaWebhookController::class)
    ->middleware('throttle:120,1')
    ->withoutMiddleware([PreventRequestForgery::class])
    ->name('webhooks.chapa');

// The customer's landing page after paying. Public, so no permission check; throttled because each
// visit may ask Chapa about the payment.
Route::get('/pay/{txRef}/return', [PaymentReturnController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('payments.return');