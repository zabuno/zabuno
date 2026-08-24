<?php

declare(strict_types=1);

use App\Http\Controllers\Billing\ReceiveIyzicoSandboxCallbackController;
use App\Http\Controllers\Billing\ReceiveIyzicoSandboxWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/iyzico-sandbox', ReceiveIyzicoSandboxWebhookController::class);
Route::post('/billing/iyzico-sandbox/callback', ReceiveIyzicoSandboxCallbackController::class);
