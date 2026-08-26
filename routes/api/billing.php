<?php

declare(strict_types=1);

use App\Http\Controllers\Billing\ListPlansController;
use App\Http\Controllers\Billing\ShowIyzicoSandboxSessionController;
use App\Http\Controllers\Billing\ShowSubscriptionController;
use App\Http\Controllers\Billing\StoreIyzicoSandboxSessionController;
use App\Http\Controllers\Entitlement\ShowWorkspaceEntitlementsController;
use App\Http\Controllers\Ledger\ShowWorkspaceLedgerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/workspaces/{workspace}/entitlements', ShowWorkspaceEntitlementsController::class);
    Route::get('/workspaces/{workspace}/plans', ListPlansController::class);
    Route::get('/workspaces/{workspace}/subscription', ShowSubscriptionController::class);
    Route::get('/workspaces/{workspace}/ledger', ShowWorkspaceLedgerController::class);

    Route::get('/workspaces/{workspace}/iyzico-sandbox/session', ShowIyzicoSandboxSessionController::class);
    Route::post('/workspaces/{workspace}/iyzico-sandbox/session', StoreIyzicoSandboxSessionController::class);
});
