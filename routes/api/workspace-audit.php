<?php

declare(strict_types=1);

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
});
