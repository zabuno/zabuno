<?php

declare(strict_types=1);

use App\Http\Controllers\PlatformAdmin\ListSupportRequestsController;
use App\Http\Controllers\PlatformAdmin\ReplyToSupportRequestController;
use App\Http\Controllers\PlatformAdmin\UpdateSupportRequestStatusController;
use App\Http\Controllers\Support\ListWorkspaceSupportRequestsController;
use App\Http\Controllers\Support\StoreWorkspaceSupportRequestController;
use App\Http\Middleware\EnsurePlatformSuperAdmin;
use Illuminate\Support\Facades\Route;

/*
    DESTEK KANALI — FF-201 (`docs/125`, `docs/107` Faz 1.6).

    Panel tarafı: sahibin kendi çalışma alanından açtığı talepler ve yeni
    talep. Yazma ucu hız sınırlı (`throttle:5,1`, ekip davetiyle aynı):
    sınırsız bir talep ucu, destek kuyruğunu doldurmanın en ucuz yolu olurdu.

    Süperadmin uçları da BU dosyada, `platform-admin.php`te değil: destek
    tek bir alandır ve alanın okuma/yazma yüzeyleri bir arada durmalı.
    Ekranı yok (`PlatformApp.tsx` başka pakette) — uçlar, ekran geldiğinde
    sunucu hazır olsun diye var.

    REFERANSLA KAMUYA AÇIK DURUM SORGUSU YOKTUR ve olmayacak: referans
    kişisel veri anahtarı olurdu (`docs/125` §5). Durum yalnız panelde ve
    e-postadadır.
*/
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/workspaces/{workspace}/support-requests', ListWorkspaceSupportRequestsController::class);
    Route::post('/workspaces/{workspace}/support-requests', StoreWorkspaceSupportRequestController::class)->middleware('throttle:5,1');

    Route::middleware(EnsurePlatformSuperAdmin::class)->group(function () {
        Route::get('/admin/support-requests', ListSupportRequestsController::class);
        Route::put('/admin/support-requests/{supportRequest}/status', UpdateSupportRequestStatusController::class)->middleware('throttle:20,1');
        /*
            CEVAP UCU, durum ucuyla AYNI sınırda: aynı elin aynı ekrandaki
            iki hareketi. UÇ EXACTLY-ONCE DEĞİLDİR — sunucuda yinelenen
            gönderimi eleyen bir anahtar yok, iki eşzamanlı POST iki
            e-posta üretir (`docs/125` §6).
        */
        Route::post('/admin/support-requests/{supportRequest}/reply', ReplyToSupportRequestController::class)->middleware('throttle:20,1');
    });
});
