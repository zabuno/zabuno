<?php

declare(strict_types=1);

use App\Http\Controllers\Reference\ShowMarketReferenceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // Kimlik doğrulaması ARKASINDA: hassas değil ama açık bırakmanın da
    // bir faydası yok, ve açık bir uç nokta bedava bant genişliğidir.
    Route::get('/reference/markets', ShowMarketReferenceController::class);
});
