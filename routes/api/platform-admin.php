<?php

declare(strict_types=1);

use App\Http\Controllers\PlatformAdmin\ActivateManagedPlanController;
use App\Http\Controllers\PlatformAdmin\DisableProviderCredentialController;
use App\Http\Controllers\PlatformAdmin\ListManagedPlansController;
use App\Http\Controllers\PlatformAdmin\ListManagedWorkspacesController;
use App\Http\Controllers\PlatformAdmin\ListProviderConnectionsController;
use App\Http\Controllers\PlatformAdmin\ListProviderCredentialsController;
use App\Http\Controllers\PlatformAdmin\SetProviderConnectionStateController;
use App\Http\Controllers\PlatformAdmin\ShowManagedSubscriptionController;
use App\Http\Controllers\PlatformAdmin\StoreManagedPlanController;
use App\Http\Controllers\PlatformAdmin\StoreManualPaymentController;
use App\Http\Controllers\PlatformAdmin\StoreProviderConnectionController;
use App\Http\Controllers\PlatformAdmin\StoreProviderCredentialController;
use App\Http\Controllers\PlatformAdmin\UpdateProviderConnectionController;
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

        // Sağlayıcı kimlik-bilgisi kasası — `docs/94`. Yazma uçları
        // superadmin arkasında ve throttle'lı; yine de sır cevaba çıkmaz.
        Route::get('/admin/credentials', ListProviderCredentialsController::class);
        Route::put('/admin/credentials/{provider}', StoreProviderCredentialController::class)->middleware('throttle:20,1');
        Route::post('/admin/credentials/{provider}/disable', DisableProviderCredentialController::class)->middleware('throttle:20,1');

        /*
            Çok-bağlantı yüzeyi — `docs/95` Faz 3. Üstteki sağlayıcı-düzeyi
            uçlar KALDIRILMADI: onlar aynı verinin "varsayılan bağlantı"
            kısayolu ve Faz 2'den beri yayınlanmış yüzey. Silme ucu bilerek
            YOK — kapatmak silmek değildir; yanlışlıkla kapatılan bir hesap
            anahtar yeniden girilmeden geri açılabilmeli.
        */
        Route::get('/admin/connections', ListProviderConnectionsController::class);
        Route::post('/admin/connections', StoreProviderConnectionController::class)->middleware('throttle:20,1');
        Route::put('/admin/connections/{connection}', UpdateProviderConnectionController::class)->middleware('throttle:20,1');
        Route::post('/admin/connections/{connection}/{state}', SetProviderConnectionStateController::class)
            ->whereIn('state', ['disable', 'enable'])
            ->middleware('throttle:20,1');
    });
});
