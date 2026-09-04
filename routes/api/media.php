<?php

declare(strict_types=1);

use App\Http\Controllers\Media\CreateOriginalDownloadLinkController;
use App\Http\Controllers\Media\DeleteMediaController;
use App\Http\Controllers\Media\DeleteMediaFolderController;
use App\Http\Controllers\Media\DetachMediaUsagesController;
use App\Http\Controllers\Media\ListMediaAuditsController;
use App\Http\Controllers\Media\ListMediaController;
use App\Http\Controllers\Media\ListMediaFoldersController;
use App\Http\Controllers\Media\ListMediaVersionsController;
use App\Http\Controllers\Media\ListSlotPoliciesController;
use App\Http\Controllers\Media\MoveMediaToFolderController;
use App\Http\Controllers\Media\RenameMediaFolderController;
use App\Http\Controllers\Media\ReprocessMediaController;
use App\Http\Controllers\Media\RestoreMediaController;
use App\Http\Controllers\Media\RestoreMediaVersionController;
use App\Http\Controllers\Media\ShowMediaQuotaController;
use App\Http\Controllers\Media\ShowMediaUsagesController;
use App\Http\Controllers\Media\StoreMediaController;
use App\Http\Controllers\Media\StoreMediaFolderController;
use App\Http\Controllers\Media\UpdateMediaAltTextController;
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
    // Alt metni düzelt (`docs/49` §5.2 re-naming, FF-76).
    Route::patch('/workspaces/{workspace}/media/{media}', UpdateMediaAltTextController::class);

    // KOTA ve ASIL İNDİRME (`docs/49` Faz 6-7, `docs/98` FF-71).
    Route::get('/workspaces/{workspace}/media/quota', ShowMediaQuotaController::class);
    // Denetim izi (`docs/49` Faz 7 madde 4): kim ne zaman ne yaptı.
    Route::get('/workspaces/{workspace}/media/audits', ListMediaAuditsController::class);
    Route::post('/workspaces/{workspace}/media/{media}/download-link', CreateOriginalDownloadLinkController::class);

    /*
        KLASÖRLER (`docs/108` §3 madde 1). Elli fotoğraf tek düz listede
        duruyordu; arama yalnız adını hatırladığın dosyayı bulur. Klasör
        yolu `/media/folders` altında toplanır — `/media/{media}` yolları
        hep bir alt segmentle devam ettiği için ikisi çakışmaz.

        Silme ve taşıma hız sınırsızdır: ikisi de tek bir satır günceller,
        dış bir maliyet doğurmaz.
    */
    Route::get('/workspaces/{workspace}/media/folders', ListMediaFoldersController::class);
    Route::post('/workspaces/{workspace}/media/folders', StoreMediaFolderController::class);
    Route::patch('/workspaces/{workspace}/media/folders/{folder}', RenameMediaFolderController::class);
    Route::delete('/workspaces/{workspace}/media/folders/{folder}', DeleteMediaFolderController::class);
    Route::put('/workspaces/{workspace}/media/{media}/folder', MoveMediaToFolderController::class);
});
