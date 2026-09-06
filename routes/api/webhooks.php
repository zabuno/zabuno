<?php

declare(strict_types=1);

use App\Http\Controllers\Billing\ReceiveIyzicoCallbackController;
use App\Http\Controllers\Billing\ReceiveIyzicoSandboxCallbackController;
use App\Http\Controllers\Billing\ReceiveIyzicoSandboxWebhookController;
use App\Http\Controllers\Billing\ReceiveIyzicoWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/iyzico-sandbox', ReceiveIyzicoSandboxWebhookController::class);
Route::post('/billing/iyzico-sandbox/callback', ReceiveIyzicoSandboxCallbackController::class);

// Üretim uçları (docs/107 Faz 1.1): aynı imza doğrulaması ve tekrar
// koruması; gizli anahtar işlemin kipine göre seçilir. Sandbox uçları kalır.
Route::post('/webhooks/iyzico', ReceiveIyzicoWebhookController::class);
Route::post('/billing/iyzico/callback', ReceiveIyzicoCallbackController::class);
