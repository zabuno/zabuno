<?php

declare(strict_types=1);

use App\Http\Controllers\Analytics\ShowAnalyticsSummaryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/workspaces/{workspace}/brand/locations/{location}/analytics/summary', ShowAnalyticsSummaryController::class);
});
