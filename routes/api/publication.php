<?php

declare(strict_types=1);

use App\Http\Controllers\Publication\ShowCurrentPublicationController;
use App\Http\Controllers\Publication\StorePublicationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('/workspaces/{workspace}/menu/{menu}/publications', StorePublicationController::class);
    Route::get('/workspaces/{workspace}/menu/{menu}/publications/current', ShowCurrentPublicationController::class);
});
