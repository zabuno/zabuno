<?php

declare(strict_types=1);

use App\Http\Controllers\Analytics\ShowAnalyticsSummaryController;
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
});
