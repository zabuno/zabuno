<?php

declare(strict_types=1);

use App\Http\Controllers\Billing\DestroyPlanChangeController;
use App\Http\Controllers\Billing\DestroySubscriptionCancellationController;
use App\Http\Controllers\Billing\DownloadInvoiceDocumentController;
use App\Http\Controllers\Billing\ListInvoicesController;
use App\Http\Controllers\Billing\ListPlansController;
use App\Http\Controllers\Billing\ShowBillingProfileController;
use App\Http\Controllers\Billing\ShowCheckoutStatusController;
use App\Http\Controllers\Billing\ShowIyzicoSandboxSessionController;
use App\Http\Controllers\Billing\ShowPlanChangeController;
use App\Http\Controllers\Billing\ShowSubscriptionController;
use App\Http\Controllers\Billing\StoreBillingProfileController;
use App\Http\Controllers\Billing\StoreCheckoutController;
use App\Http\Controllers\Billing\StoreIyzicoSandboxSessionController;
use App\Http\Controllers\Billing\StorePlanChangeController;
use App\Http\Controllers\Billing\StoreSubscriptionCancellationController;
use App\Http\Controllers\Entitlement\ShowWorkspaceEntitlementsController;
use App\Http\Controllers\Ledger\ShowWorkspaceLedgerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/workspaces/{workspace}/entitlements', ShowWorkspaceEntitlementsController::class);
    Route::get('/workspaces/{workspace}/plans', ListPlansController::class);
    Route::get('/workspaces/{workspace}/subscription', ShowSubscriptionController::class);

    /*
        ABONELİĞİN EKSİK YARISI (docs/107 Faz 1.3, docs/134).

        İptal SAKLANMAZ ve zorlaştırılmaz: uzaktan satışta çıkış yolunun
        girişten daha zor olması, tüketici hukukunun tam olarak hoşlanmadığı
        şeydir. Ama tek tıkla da olmaz — panel önce ne olacağını yazar.

        İptal ve cayma AYNI KAYNAĞIN iki yönüdür (POST/DELETE .../cancellation);
        plan değişikliği de öyle. Yükseltmenin yolu burası DEĞİL, ödemedir:
        düşürme uçları yükseltmeyi 422 `not_a_downgrade` ile geri çevirir.

        Hız sınırı iade/ödeme uçlarından GEVŞEKTİR ve olmalıdır: bu uçlar
        sağlayıcıya gitmez, yalnız kendi satırımızı günceller — sıkı bir
        sınır, fikrini değiştiren sahibi kapının dışında bırakırdı.
    */
    Route::post('/workspaces/{workspace}/subscription/cancellation', StoreSubscriptionCancellationController::class)
        ->middleware('throttle:20,1');
    Route::delete('/workspaces/{workspace}/subscription/cancellation', DestroySubscriptionCancellationController::class)
        ->middleware('throttle:20,1');
    Route::get('/workspaces/{workspace}/subscription/plan-change', ShowPlanChangeController::class);
    Route::post('/workspaces/{workspace}/subscription/plan-change', StorePlanChangeController::class)
        ->middleware('throttle:20,1');
    Route::delete('/workspaces/{workspace}/subscription/plan-change', DestroyPlanChangeController::class)
        ->middleware('throttle:20,1');
    Route::get('/workspaces/{workspace}/ledger', ShowWorkspaceLedgerController::class);

    Route::get('/workspaces/{workspace}/iyzico-sandbox/session', ShowIyzicoSandboxSessionController::class);
    Route::post('/workspaces/{workspace}/iyzico-sandbox/session', StoreIyzicoSandboxSessionController::class);

    /*
        KENDİ KENDİNE ABONELİK (docs/107 Faz 1.1 + 1.3, docs/123).

        Plan seçimi → fatura profili → ödeme. Tutar sunucudan; profil
        eksikken 422 adıyla; geçit etkin kipe göre. Sandbox oturum uçları
        KALDIRILMADI: eski yüzey dondurulmuş, yeni yüzey ayrı. Başlatma
        ucu hız sınırlı: her istek sağlayıcıda bir oturum açar.
    */
    Route::get('/workspaces/{workspace}/billing-profile', ShowBillingProfileController::class);
    Route::put('/workspaces/{workspace}/billing-profile', StoreBillingProfileController::class);
    Route::get('/workspaces/{workspace}/checkout', ShowCheckoutStatusController::class);
    Route::post('/workspaces/{workspace}/checkout', StoreCheckoutController::class)->middleware('throttle:10,1');

    /*
        FATURA (docs/107 Faz 1.4, docs/130).

        Tahsilatın karşılığındaki belge; okunur ve indirilir, YAZILMAZ.
        Defterle aynı kapı (`billing.view`) ve aynı sessizlik: yetkisiz
        istek varlığı bile sızdırmaz. PDF ucu hız sınırlı — her istek bir
        A4 belgesi üretir.
    */
    Route::get('/workspaces/{workspace}/invoices', ListInvoicesController::class);
    Route::get('/workspaces/{workspace}/invoices/{invoice}/document.pdf', DownloadInvoiceDocumentController::class)
        ->whereNumber('invoice')
        ->middleware('throttle:30,1');
});
