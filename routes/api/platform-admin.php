<?php

declare(strict_types=1);

use App\Http\Controllers\PlatformAdmin\ActivateManagedPlanController;
use App\Http\Controllers\PlatformAdmin\ListManagedPlansController;
use App\Http\Controllers\PlatformAdmin\ListManagedWorkspacesController;
use App\Http\Controllers\PlatformAdmin\ShowManagedSubscriptionController;
use App\Http\Controllers\PlatformAdmin\StoreManagedPlanController;
use App\Http\Controllers\PlatformAdmin\StoreManualPaymentController;
use App\Http\Middleware\EnsurePlatformSuperAdmin;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::middleware(EnsurePlatformSuperAdmin::class)->group(function () {
        Route::get('/admin/plans', ListManagedPlansController::class);
        Route::post('/admin/plans', StoreManagedPlanController::class);
        Route::post('/admin/plans/{plan}/activate', ActivateManagedPlanController::class);

        Route::get('/admin/workspaces', ListManagedWorkspacesController::class);
        Route::get('/admin/workspaces/{workspace}/subscription', ShowManagedSubscriptionController::class);
        Route::post('/admin/workspaces/{workspace}/manual-payments', StoreManualPaymentController::class)->middleware('throttle:5,1');
    });
});
