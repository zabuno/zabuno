<?php

declare(strict_types=1);

use App\Http\Controllers\Analytics\ShowAnalyticsSummaryController;
use App\Http\Controllers\Analytics\ShowMenuEngineeringController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/workspaces/{workspace}/brand/locations/{location}/analytics/summary', ShowAnalyticsSummaryController::class);

    /*
        MARKANIN TAMAMI — `docs/68`.

        İki şubesi olan bir işletme markanın bütününü göremiyordu: toplamı
        bulmak için şubeleri tek tek gezip kafadan toplamak gerekiyordu. Üst
        çubuktaki "All locations" bağlamının analitikte karşılığı yoktu.
    */
    Route::get('/workspaces/{workspace}/analytics/summary', ShowAnalyticsSummaryController::class);

    /*
        MENÜ MÜHENDİSLİĞİ — `docs/84`.

        "Menün 214 kez açıldı" menüyü DEĞİŞTİRMEK için hiçbir şey söylemez:
        hangi ürünü büyütmeli, hangisini çıkarmalı, hangi talebi
        karşılamıyorum?
    */
    Route::get('/workspaces/{workspace}/analytics/menu-engineering', ShowMenuEngineeringController::class);
});
