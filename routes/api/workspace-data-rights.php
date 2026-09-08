<?php

declare(strict_types=1);

use App\Http\Controllers\Workspace\CancelWorkspaceErasureController;
use App\Http\Controllers\Workspace\RequestWorkspaceDataExportController;
use App\Http\Controllers\Workspace\RequestWorkspaceErasureController;
use App\Http\Controllers\Workspace\ShowWorkspaceDataRightsController;
use Illuminate\Support\Facades\Route;

/*
    VERİ HAKLARI (FF-226, `docs/107` Faz 3.3, `docs/138`).

    Kendi dosyasında, çünkü tek bir modüle ait değil: kapsamı bütün
    modüllerin tablolarıdır. Bir modül dosyasına konsaydı, o modülün
    parçası sanılırdı — ve bir gün o modül kaldırıldığında kiracının veri
    hakkı da onunla gitmiş olurdu.

    HIZ SINIRI VAR: dışa aktarma bir çalışma alanının bütün tablolarını
    okur ve arka arkaya basılan bir düğme, kuyruğu tek bir kiracıyla
    doldurabilirdi.
*/
Route::middleware(['auth:sanctum', 'verified'])->group(function (): void {
    Route::get('/workspaces/{workspace}/data-rights', ShowWorkspaceDataRightsController::class);

    Route::middleware('throttle:10,1')->group(function (): void {
        Route::post('/workspaces/{workspace}/data-rights/exports', RequestWorkspaceDataExportController::class);
        Route::post('/workspaces/{workspace}/data-rights/erasure', RequestWorkspaceErasureController::class);
        Route::delete('/workspaces/{workspace}/data-rights/erasure/{dataRequest}', CancelWorkspaceErasureController::class);
    });
});
