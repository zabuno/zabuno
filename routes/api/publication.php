<?php

declare(strict_types=1);

use App\Http\Controllers\Publication\CancelPublicationScheduleController;
use App\Http\Controllers\Publication\CreateDraftPreviewLinkController;
use App\Http\Controllers\Publication\ListPublicationsController;
use App\Http\Controllers\Publication\RestorePublicationController;
use App\Http\Controllers\Publication\ShowCurrentPublicationController;
use App\Http\Controllers\Publication\ShowPublicationScheduleController;
use App\Http\Controllers\Publication\StorePublicationController;
use App\Http\Controllers\Publication\StorePublicationScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('/workspaces/{workspace}/menu/{menu}/publications', StorePublicationController::class);
    Route::get('/workspaces/{workspace}/menu/{menu}/publications/current', ShowCurrentPublicationController::class);

    /*
        PLANLA — zamanlanmış yayın (sahibin 2026-09-05 kararı).

        Bu üç rota `publications` liste rotasından ÖNCE tanımlıdır. Sıra
        bozulursa `publications/schedule` adresi, `{publication}` kimliği
        "schedule" olan bir yayın sanılırdı.
    */
    Route::get('/workspaces/{workspace}/menu/{menu}/publications/schedule', ShowPublicationScheduleController::class);
    Route::post('/workspaces/{workspace}/menu/{menu}/publications/schedule', StorePublicationScheduleController::class);
    Route::delete('/workspaces/{workspace}/menu/{menu}/publications/schedule/{schedule}', CancelPublicationScheduleController::class)
        ->where('schedule', '[0-9]+');

    /*
        TELEFONDA ÖNİZLE: burada yalnız İMZALI ADRES üretilir. Önizlemenin
        kendisi web tarafındadır ve oturum istemez — sahip onu telefonunda
        açar, panele girmiş olması beklenemez.
    */
    Route::post('/workspaces/{workspace}/menu/{menu}/draft-preview-link', CreateDraftPreviewLinkController::class);

    /*
        Yanlış yayından dönmek (`docs/81`).

        `current` rotası BU İKİSİNDEN ÖNCE tanımlıdır: `{publication}` bir
        sayıya sınırlansa bile, sıra bozulursa "current" bir kimlik sanılır.
    */
    Route::get('/workspaces/{workspace}/menu/{menu}/publications', ListPublicationsController::class);
    Route::post('/workspaces/{workspace}/menu/{menu}/publications/{publication}/restore', RestorePublicationController::class)
        ->where('publication', '[0-9]+');
});
