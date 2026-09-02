<?php

declare(strict_types=1);

use App\Http\Controllers\Publication\ListPublicationsController;
use App\Http\Controllers\Publication\RestorePublicationController;
use App\Http\Controllers\Publication\ShowCurrentPublicationController;
use App\Http\Controllers\Publication\StorePublicationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('/workspaces/{workspace}/menu/{menu}/publications', StorePublicationController::class);
    Route::get('/workspaces/{workspace}/menu/{menu}/publications/current', ShowCurrentPublicationController::class);

    /*
        Yanlış yayından dönmek (`docs/81`).

        `current` rotası BU İKİSİNDEN ÖNCE tanımlıdır: `{publication}` bir
        sayıya sınırlansa bile, sıra bozulursa "current" bir kimlik sanılır.
    */
    Route::get('/workspaces/{workspace}/menu/{menu}/publications', ListPublicationsController::class);
    Route::post('/workspaces/{workspace}/menu/{menu}/publications/{publication}/restore', RestorePublicationController::class)
        ->where('publication', '[0-9]+');
});
