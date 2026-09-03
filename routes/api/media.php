<?php

declare(strict_types=1);

use App\Http\Controllers\Media\DeleteMediaController;
use App\Http\Controllers\Media\DetachMediaUsagesController;
use App\Http\Controllers\Media\ListMediaController;
use App\Http\Controllers\Media\ListMediaVersionsController;
use App\Http\Controllers\Media\ListSlotPoliciesController;
use App\Http\Controllers\Media\ReprocessMediaController;
use App\Http\Controllers\Media\RestoreMediaController;
use App\Http\Controllers\Media\RestoreMediaVersionController;
use App\Http\Controllers\Media\ShowMediaUsagesController;
use App\Http\Controllers\Media\StoreMediaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('/workspaces/{workspace}/media', StoreMediaController::class);
    Route::get('/workspaces/{workspace}/media', ListMediaController::class);
    // Slot politikaları workspace'e bağlı DEĞİLDİR: ürünün kendi kuralları.
    Route::get('/media/slot-policies', ListSlotPoliciesController::class);
    Route::delete('/workspaces/{workspace}/media/{media}', DeleteMediaController::class);

    /*
        SÜRÜMLER (`docs/49` Faz 3, `docs/98` FF-69). Asıl değişmez; yeniden
        üretim ve geri alma yeni sürüm açar, hiçbir satır silinmez. Yeniden
        üretim hız sınırlı: her çağrı görsel işler.
    */
    Route::get('/workspaces/{workspace}/media/{media}/versions', ListMediaVersionsController::class);
    Route::post('/workspaces/{workspace}/media/{media}/reprocess', ReprocessMediaController::class)
        ->middleware('throttle:10,1');
    Route::post('/workspaces/{workspace}/media/{media}/versions/{version}/restore', RestoreMediaVersionController::class);

    /*
        KULLANIM GRAFİĞİ ve ÇÖP (`docs/49` Faz 4-5, `docs/98` FF-70). Silme
        artık çöpe atar; geri alınabilir; süresi dolan `media:purge-trash`
        ile kalıcı gider. "Nerede kullanılıyor?" silmeden önce sorulur.
    */
    Route::get('/workspaces/{workspace}/media/{media}/usages', ShowMediaUsagesController::class);
    Route::post('/workspaces/{workspace}/media/{media}/detach', DetachMediaUsagesController::class);
    Route::post('/workspaces/{workspace}/media/{media}/restore', RestoreMediaController::class);
});
