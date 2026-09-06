<?php

declare(strict_types=1);

use App\Http\Controllers\Workspace\ShowSetupProgressController;
use App\Http\Controllers\Workspace\ShowWorkspaceAuditTrailController;
use Illuminate\Support\Facades\Route;

/*
    DENETİM İZİ (FF-132) — Ayarlar'ın dördüncü sekmesi.

    Kendi dosyasında duruyor çünkü kaynağı tek bir modül DEĞİL: medya izi ve
    yayın geçmişi birleşiyor, yarın fatura ve takım da eklenebilir. Bir modül
    dosyasına konsaydı iz, o modülün parçası sanılırdı.
*/
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/workspaces/{workspace}/audit-trail', ShowWorkspaceAuditTrailController::class);

    /*
        KURULUM İLERLEMESİ (`docs/107` 1.7) — aynı sebeple burada: beş
        modülün tablosundan okunur (marka, şube, menü, yayın, karekod) ve
        hiçbirine ait değildir. İlk yayına kadar geçen süre de buradan gelir;
        yeni tablo yok, iki mevcut zaman damgasının farkı.
    */
    Route::get('/workspaces/{workspace}/setup-progress', ShowSetupProgressController::class);
});
