<?php

declare(strict_types=1);

use App\Http\Controllers\Billing\DownloadInvoiceDocumentController;
use App\Http\Controllers\Billing\ListInvoicesController;
use App\Http\Controllers\Billing\ListPlansController;
use App\Http\Controllers\Billing\ShowBillingProfileController;
use App\Http\Controllers\Billing\ShowCheckoutStatusController;
use App\Http\Controllers\Billing\ShowIyzicoSandboxSessionController;
use App\Http\Controllers\Billing\ShowSubscriptionController;
use App\Http\Controllers\Billing\StoreBillingProfileController;
use App\Http\Controllers\Billing\StoreCheckoutController;
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
