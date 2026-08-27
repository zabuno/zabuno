<?php

declare(strict_types=1);

use App\Http\Controllers\Media\DeleteMediaController;
use App\Http\Controllers\Media\ListMediaController;
use App\Http\Controllers\Media\ListSlotPoliciesController;
use App\Http\Controllers\Media\StoreMediaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('/workspaces/{workspace}/media', StoreMediaController::class);
    Route::get('/workspaces/{workspace}/media', ListMediaController::class);
    // Slot politikaları workspace'e bağlı DEĞİLDİR: ürünün kendi kuralları.
    Route::get('/media/slot-policies', ListSlotPoliciesController::class);
    Route::delete('/workspaces/{workspace}/media/{media}', DeleteMediaController::class);
});
