<?php

declare(strict_types=1);

use App\Http\Controllers\Analytics\ShowAnalyticsSummaryController;
use App\Http\Controllers\Analytics\ShowAnalyticsTimeSeriesController;
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

    /*
        ZAMAN SERİSİ — `docs/109` §1 (Insights), §6.5.

        Aralık toplamı bir haftanın ŞEKLİNİ gizliyordu: hangi gün çöktü,
        hangi saatte yoğunlaştı, geçen haftaya göre nasıl, hangi şube
        çekiyor. Insights ekranının çubuk+çizgi grafiği, saat ısı haritası
        ve şube halkası bu uçtan besleniyor.

        İki adres: biri seçili şube, diğeri markanın tamamı — `summary` ile
        aynı ikili, çünkü sahibin üst çubuktaki "tüm şubeler" bağlamı
        analitikte de karşılık bulmalı (`docs/68`).
    */
    Route::get('/workspaces/{workspace}/brand/locations/{location}/analytics/time-series', ShowAnalyticsTimeSeriesController::class);
    Route::get('/workspaces/{workspace}/analytics/time-series', ShowAnalyticsTimeSeriesController::class);
});
