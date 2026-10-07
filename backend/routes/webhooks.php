<?php

use App\Http\Controllers\Webhooks\CourierWebhookController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

// Unversioned: the provider's payload is the contract (API.md §1.1). CSRF-exempt (stateless API route).
Route::post('webhooks/payment/{provider}', PaymentWebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('webhooks.payment');

Route::post('webhooks/delivery-updates', CourierWebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('webhooks.courier');
