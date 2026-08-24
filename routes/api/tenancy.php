<?php

declare(strict_types=1);

use App\Http\Controllers\Tenancy\CreateWorkspaceController;
use App\Http\Controllers\Tenancy\CurrentWorkspaceContextController;
use App\Http\Controllers\Tenancy\ListLocationsController;
use App\Http\Controllers\Tenancy\ListWorkspacesController;
use App\Http\Controllers\Tenancy\ShowBrandController;
use App\Http\Controllers\Tenancy\ShowLocationController;
use App\Http\Controllers\Tenancy\StoreBrandController;
use App\Http\Controllers\Tenancy\StoreLocationController;
use App\Http\Controllers\Tenancy\SwitchWorkspaceContextController;
use App\Http\Controllers\Tenancy\UpdateBrandController;
use App\Http\Controllers\Tenancy\UpdateLocationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('/workspaces', CreateWorkspaceController::class)->middleware('throttle:5,1');
    Route::get('/workspaces', ListWorkspacesController::class);
    Route::put('/workspace-context', SwitchWorkspaceContextController::class);
    Route::get('/workspace-context', CurrentWorkspaceContextController::class);

    Route::post('/workspaces/{workspace}/brand', StoreBrandController::class);
    Route::get('/workspaces/{workspace}/brand', ShowBrandController::class);
    Route::put('/workspaces/{workspace}/brand', UpdateBrandController::class);

    Route::post('/workspaces/{workspace}/brand/locations', StoreLocationController::class);
    Route::get('/workspaces/{workspace}/brand/locations', ListLocationsController::class);
    Route::get('/workspaces/{workspace}/brand/locations/{location}', ShowLocationController::class);
    Route::put('/workspaces/{workspace}/brand/locations/{location}', UpdateLocationController::class);
});
