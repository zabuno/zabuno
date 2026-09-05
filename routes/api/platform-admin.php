<?php

declare(strict_types=1);

use App\Http\Controllers\PlatformAdmin\ActivateManagedPlanController;
use App\Http\Controllers\PlatformAdmin\DisableProviderCredentialController;
use App\Http\Controllers\PlatformAdmin\ListCoreModulesController;
use App\Http\Controllers\PlatformAdmin\ListManagedPlansController;
use App\Http\Controllers\PlatformAdmin\ListManagedUsersController;
use App\Http\Controllers\PlatformAdmin\ListManagedWorkspacesController;
use App\Http\Controllers\PlatformAdmin\ListPlatformAuditLogController;
use App\Http\Controllers\PlatformAdmin\ListProviderConnectionsController;
use App\Http\Controllers\PlatformAdmin\ListProviderCredentialsController;
use App\Http\Controllers\PlatformAdmin\ProbeProviderConnectionController;
use App\Http\Controllers\PlatformAdmin\SetProviderConnectionStateController;
use App\Http\Controllers\PlatformAdmin\ShowAiAuditController;
use App\Http\Controllers\PlatformAdmin\ShowManagedSubscriptionController;
use App\Http\Controllers\PlatformAdmin\ShowManagedWorkspaceController;
use App\Http\Controllers\PlatformAdmin\StoreManagedPlanController;
use App\Http\Controllers\PlatformAdmin\StoreManualPaymentController;
use App\Http\Controllers\PlatformAdmin\StoreProviderConnectionController;
use App\Http\Controllers\PlatformAdmin\StoreProviderCredentialController;
use App\Http\Controllers\PlatformAdmin\StoreReleaseAttestationController;
use App\Http\Controllers\PlatformAdmin\UpdateProviderConnectionController;
use App\Http\Middleware\EnsurePlatformSuperAdmin;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::middleware(EnsurePlatformSuperAdmin::class)->group(function () {
        Route::get('/admin/plans', ListManagedPlansController::class);
        Route::post('/admin/plans', StoreManagedPlanController::class);
        Route::post('/admin/plans/{plan}/activate', ActivateManagedPlanController::class);

        Route::get('/admin/workspaces', ListManagedWorkspacesController::class);
        /*
            Kiracı ayrıntısı (`docs/122` Y2). Liste vardı, satıra tıklayınca
            hiçbir şey yoktu. SALT OKUNUR: bu ucun POST/PUT/DELETE eşi
            bilerek yoktur ve kiracı olarak oturum açma (impersonation) Y7'ye
            bırakılmıştır — `docs/122` §5 onu en tehlikeli süperadmin
            yeteneği sayar ve zor olmasını şart koşar.
        */
        Route::get('/admin/workspaces/{workspace}', ShowManagedWorkspaceController::class)
            ->whereNumber('workspace');
        Route::get('/admin/workspaces/{workspace}/subscription', ShowManagedSubscriptionController::class);

        /*
            Kullanıcı görünürlüğü (`docs/122` Y2): kim, hangi çalışma
            alanında, hangi rolle, adresi doğrulanmış mı. Parola sıfırlama
            ya da kilitleme ucu YOK — istenen görünürlüktü, müdahale değil.
        */
        Route::get('/admin/users', ListManagedUsersController::class);

        /*
            Denetim günlüğü ekranının ucu (`docs/122` Y2). Dört tablo aylardır
            doluyordu ve okuyan yeri yoktu; okunmayan denetim izi yoktur.
        */
        Route::get('/admin/audit-log', ListPlatformAuditLogController::class);
        Route::post('/admin/workspaces/{workspace}/manual-payments', StoreManualPaymentController::class)->middleware('throttle:5,1');

        // Sağlayıcı kimlik-bilgisi kasası — `docs/94`. Yazma uçları
        // superadmin arkasında ve throttle'lı; yine de sır cevaba çıkmaz.
        // AI denetim izi (`docs/98` FF-66): kim hangi anahtarı ne zaman
        // yazdı, hangi tenant hangi hesaba yapıştı. Sır taşımaz.
        Route::get('/admin/ai/audit', ShowAiAuditController::class);

        /*
            Modül envanteri (`docs/111` adım 1) — mühendislik kanıtı, ticaret
            değil; ekranı da `/platform` altında değil `/engineering` altında.
            Yalnız OKUMA: modül açma/kapama bu depoda modellenmiş değil, o
            yüzden bir yazma ucu da yok (`docs/111` §5.1).
        */
        Route::get('/admin/modules', ListCoreModulesController::class);

        // İnsan tanıklığı kaydı (`docs/98` FF-63) — yalnız superadmin.
        Route::post('/admin/release-attestations', StoreReleaseAttestationController::class)->middleware('throttle:20,1');

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
        /*
            Uyumluluk yoklaması (`docs/95` Faz 3). Ayrı ve DAHA SIKI hız
            sınırı: dışarıya gerçek bir ağ çağrısı yapar, token harcamasa
            bile sınırsız denemeye açık bırakılmaz.
        */
        Route::post('/admin/connections/{connection}/probe', ProbeProviderConnectionController::class)
            ->middleware('throttle:10,1');
        Route::post('/admin/connections/{connection}/{state}', SetProviderConnectionStateController::class)
            ->whereIn('state', ['disable', 'enable'])
            ->middleware('throttle:20,1');
    });
});
