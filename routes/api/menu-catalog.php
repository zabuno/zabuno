<?php

declare(strict_types=1);

use App\Http\Controllers\Ai\ApplyMenuAiImportController;
use App\Http\Controllers\Ai\ApplyProductDescriptionDraftController;
use App\Http\Controllers\Ai\ShowMenuAiImportController;
use App\Http\Controllers\Ai\StoreMenuAiImportController;
use App\Http\Controllers\Ai\StoreProductDescriptionDraftController;
use App\Http\Controllers\MenuCatalog\BindMenuItemImageController;
use App\Http\Controllers\MenuCatalog\DeleteCategoryController;
use App\Http\Controllers\MenuCatalog\DeleteMenuItemController;
use App\Http\Controllers\MenuCatalog\ExportMenuCsvController;
use App\Http\Controllers\MenuCatalog\ImportMenuCsvController;
use App\Http\Controllers\MenuCatalog\RenameCategoryController;
use App\Http\Controllers\MenuCatalog\RenameMenuItemController;
use App\Http\Controllers\MenuCatalog\ReorderCategoriesController;
use App\Http\Controllers\MenuCatalog\ReorderMenuItemsController;
use App\Http\Controllers\MenuCatalog\ShowMenuController;
use App\Http\Controllers\MenuCatalog\StoreCategoryController;
use App\Http\Controllers\MenuCatalog\StoreMenuController;
use App\Http\Controllers\MenuCatalog\StoreMenuEntryController;
use App\Http\Controllers\MenuCatalog\StoreMenuItemController;
use App\Http\Controllers\MenuCatalog\StoreProductController;
use App\Http\Controllers\MenuCatalog\UpdateMenuItemAllergensController;
use App\Http\Controllers\MenuCatalog\UpdateMenuItemPriceController;
use App\Http\Controllers\MenuCatalog\UpdateMenuItemStockController;
use App\Http\Controllers\MenuCatalog\UpdateMenuItemVisibilityController;
use App\Http\Controllers\MenuCatalog\UpdateMenuStockController;
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

    /*
        MENÜYÜ İŞLETMEK — `docs/73` (P0-01).

        Ürün bu dört iş olmadan bir menüyü yayımlayabiliyor ama işletemiyordu:
        yanlış yazılan bir ürünü düzeltmenin yolu yoktu, sezonluk bir
        kategoriyi kaldırmanın yolu yoktu, sıra veri modelinde tasarlanmıştı
        ama yüzeyi yazılmamıştı.
    */
    Route::put('/workspaces/{workspace}/menu-categories/{category}', RenameCategoryController::class);
    Route::delete('/workspaces/{workspace}/menu-categories/{category}', DeleteCategoryController::class);
    Route::put('/workspaces/{workspace}/menu-items/{menuItem}', RenameMenuItemController::class);
    Route::delete('/workspaces/{workspace}/menu-items/{menuItem}', DeleteMenuItemController::class);
    // Ürüne fotoğraf bağlar; `null` bağı kaldırır (`docs/77`).
    Route::put('/workspaces/{workspace}/menu-items/{menuItem}/image', BindMenuItemImageController::class);

    /*
        "Bugün tükendi" (`docs/82`).

        Görünürlükten AYRI bir eksen ve yayın gerektirmez: "balık bitti"
        servis sırasında geçerli, dakikalık bir gerçektir.
    */
    Route::put('/workspaces/{workspace}/menu-items/{menuItem}/stock', UpdateMenuItemStockController::class);
    Route::put('/workspaces/{workspace}/menu/{menu}/stock', UpdateMenuStockController::class);

    /*
        Menüyü almak ve geri koymak (`docs/80`).

        Hız SINIRLI: her ikisi de menünün tamamını okuyup yazar ve tekrar
        tekrar çağrılmaları için bir sebep yok.
    */
    Route::get('/workspaces/{workspace}/menu/{menu}/export.csv', ExportMenuCsvController::class)
        ->middleware('throttle:20,1');
    Route::post('/workspaces/{workspace}/menu/{menu}/import', ImportMenuCsvController::class)
        ->middleware('throttle:10,1');

    /*
        Fotoğraftan menü okuma (`docs/92`).

        Okuma HIZ SINIRLI: her çağrı dış bir sağlayıcıya para ödetir ve
        sınırsız deneme, faturayı bir betiğe bıraktırırdı. Onay ayrı bir
        yoldur ve yetki orada YENİDEN doğrulanır.
    */
    Route::post('/workspaces/{workspace}/menu/{menu}/ai-imports', StoreMenuAiImportController::class)
        ->middleware('throttle:6,1');
    Route::get('/workspaces/{workspace}/ai-imports/{artifact}', ShowMenuAiImportController::class);
    Route::post('/workspaces/{workspace}/ai-imports/{artifact}/apply', ApplyMenuAiImportController::class)
        ->middleware('throttle:10,1');

    /*
        Ürün açıklaması taslağı (`docs/96`, Faz 2, `opt-23`). Aynı hız
        sınırı gerekçesi: her çağrı dış sağlayıcıya para ödetir.
    */
    Route::post('/workspaces/{workspace}/menu-items/{menuItem}/description-drafts', StoreProductDescriptionDraftController::class)
        ->middleware('throttle:6,1');
    Route::post('/workspaces/{workspace}/description-drafts/{artifact}/apply', ApplyProductDescriptionDraftController::class)
        ->middleware('throttle:10,1');

    Route::put('/workspaces/{workspace}/menu-categories/{category}/item-order', ReorderMenuItemsController::class);
    Route::put('/workspaces/{workspace}/menu/{menu}/category-order', ReorderCategoriesController::class);
});
