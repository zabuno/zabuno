<?php

declare(strict_types=1);

use App\Http\Controllers\MenuCatalog\ShowMenuController;
use App\Http\Controllers\MenuCatalog\StoreCategoryController;
use App\Http\Controllers\MenuCatalog\StoreMenuController;
use App\Http\Controllers\MenuCatalog\StoreMenuEntryController;
use App\Http\Controllers\MenuCatalog\StoreMenuItemController;
use App\Http\Controllers\MenuCatalog\StoreProductController;
use App\Http\Controllers\MenuCatalog\UpdateMenuItemAllergensController;
use App\Http\Controllers\MenuCatalog\UpdateMenuItemPriceController;
use App\Http\Controllers\MenuCatalog\UpdateMenuItemVisibilityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('/workspaces/{workspace}/brand/locations/{location}/menu', StoreMenuController::class);
    Route::get('/workspaces/{workspace}/brand/locations/{location}/menu', ShowMenuController::class);
    Route::post('/workspaces/{workspace}/menu/{menu}/categories', StoreCategoryController::class);
    Route::post('/workspaces/{workspace}/menu-categories/{category}/products', StoreProductController::class);
    Route::post('/workspaces/{workspace}/menu-categories/{category}/menu-items', StoreMenuItemController::class);
    // Menüye ürün eklemenin tek adımı: ürün + satır + alerjenler, tek işlem.
    Route::post('/workspaces/{workspace}/menu-categories/{category}/menu-entries', StoreMenuEntryController::class);
    Route::put('/workspaces/{workspace}/menu-items/{menuItem}/allergens', UpdateMenuItemAllergensController::class);
    Route::put('/workspaces/{workspace}/menu-items/{menuItem}/price', UpdateMenuItemPriceController::class);
    Route::put('/workspaces/{workspace}/menu-items/{menuItem}/visibility', UpdateMenuItemVisibilityController::class);
});
