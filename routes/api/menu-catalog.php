<?php

declare(strict_types=1);

use App\Http\Controllers\Ai\ApplyBulkMenuAiImportController;
use App\Http\Controllers\Ai\ApplyMenuAiImportController;
use App\Http\Controllers\Ai\ApplyProductDescriptionDraftController;
use App\Http\Controllers\Ai\ShowAiAvailabilityController;
use App\Http\Controllers\Ai\ShowDuplicateProductCandidatesController;
use App\Http\Controllers\Ai\ShowMenuAiBatchController;
use App\Http\Controllers\Ai\ShowMenuAiImportController;
use App\Http\Controllers\Ai\StoreBulkMenuAiImportController;
use App\Http\Controllers\Ai\StoreMenuAiBatchController;
use App\Http\Controllers\Ai\StoreMenuAiImportController;
use App\Http\Controllers\Ai\StoreProductDescriptionDraftController;
use App\Http\Controllers\MenuCatalog\BindMenuItemImageController;
use App\Http\Controllers\MenuCatalog\DeleteCategoryController;
use App\Http\Controllers\MenuCatalog\DeleteMenuController;
use App\Http\Controllers\MenuCatalog\DeleteMenuItemController;
use App\Http\Controllers\MenuCatalog\DeleteMenuServiceWindowController;
use App\Http\Controllers\MenuCatalog\ExportMenuCsvController;
use App\Http\Controllers\MenuCatalog\ImportMenuCsvController;
use App\Http\Controllers\MenuCatalog\ListLocationMenusController;
use App\Http\Controllers\MenuCatalog\ListMenuAuditsController;
use App\Http\Controllers\MenuCatalog\RenameCategoryController;
use App\Http\Controllers\MenuCatalog\RenameMenuController;
use App\Http\Controllers\MenuCatalog\RenameMenuItemController;
use App\Http\Controllers\MenuCatalog\ReorderCategoriesController;
use App\Http\Controllers\MenuCatalog\ReorderMenuItemsController;
use App\Http\Controllers\MenuCatalog\ShowMenuController;
use App\Http\Controllers\MenuCatalog\ShowMenuTreeController;
use App\Http\Controllers\MenuCatalog\StoreCategoryController;
use App\Http\Controllers\MenuCatalog\StoreMenuController;
use App\Http\Controllers\MenuCatalog\StoreMenuEntryController;
use App\Http\Controllers\MenuCatalog\StoreMenuItemController;
use App\Http\Controllers\MenuCatalog\StoreProductController;
use App\Http\Controllers\MenuCatalog\UpdateMenuItemAllergensController;
use App\Http\Controllers\MenuCatalog\UpdateMenuItemPriceController;
use App\Http\Controllers\MenuCatalog\UpdateMenuItemStockController;
use App\Http\Controllers\MenuCatalog\UpdateMenuItemVisibilityController;
use App\Http\Controllers\MenuCatalog\UpdateMenuServiceWindowController;
use App\Http\Controllers\MenuCatalog\UpdateMenuStockController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('/workspaces/{workspace}/brand/locations/{location}/menu', StoreMenuController::class);
    Route::get('/workspaces/{workspace}/brand/locations/{location}/menu', ShowMenuController::class);

    /*
        ÇOKLU MENÜ VE SAAT BAZLI GEÇİŞ — sahibin 2026-09-05 kararı
        (`docs/109` §7.1). Menü hapları bu dört yoldan beslenir: şubenin
        menülerini listelemek, bir hapa basınca O MENÜYÜ açmak, adını
        düzeltmek ve silmek.

        `{menu}` SAYIYA SINIRLI: aynı önekte `menu/duplicate-candidates`
        adında bir yol var ve sınır olmasaydı "duplicate-candidates" bir
        menü kimliği sanılırdı.
    */
    Route::get('/workspaces/{workspace}/brand/locations/{location}/menus', ListLocationMenusController::class);
    Route::get('/workspaces/{workspace}/menu/{menu}', ShowMenuTreeController::class)
        ->where('menu', '[0-9]+');
    Route::put('/workspaces/{workspace}/menu/{menu}', RenameMenuController::class)
        ->where('menu', '[0-9]+');
    Route::delete('/workspaces/{workspace}/menu/{menu}', DeleteMenuController::class)
        ->where('menu', '[0-9]+');

    /*
        Menünün saat aralığı. Kapatmak ayrı bir yoldur ve bilerek öyle:
        "aralığı kaldır" ile "boş aralık gönder" aynı şey değildir ve
        ikincisi bir gün yanlışlıkla gönderilirdi.
    */
    Route::put('/workspaces/{workspace}/menu/{menu}/service-window', UpdateMenuServiceWindowController::class)
        ->where('menu', '[0-9]+');
    Route::delete('/workspaces/{workspace}/menu/{menu}/service-window', DeleteMenuServiceWindowController::class)
        ->where('menu', '[0-9]+');
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
    /*
        TOPLU okuma (`docs/96` Faz 3). Hız sınırı TEKİL yoldan daha SIKI:
        tek istek 10 fotoğrafa kadar dış sağlayıcı çağrısı yapar, yani
        dakikada 2 istek zaten 20 çağrı demektir.
    */
    Route::post('/workspaces/{workspace}/menu/{menu}/ai-imports/batch', StoreBulkMenuAiImportController::class)
        ->middleware('throttle:2,1');

    // TOPLU ORKESTRA (`docs/98` FF-75): 40 sayfa kuyruğa, parti kalıcı
    // hafızada, toplayıcı tek listede; uygulama yine insan onaylı `apply`.
    Route::post('/workspaces/{workspace}/menu/{menu}/ai-batches', StoreMenuAiBatchController::class)
        ->middleware('throttle:2,1');
    Route::get('/workspaces/{workspace}/ai-batches/{batch}', ShowMenuAiBatchController::class);
    Route::post('/workspaces/{workspace}/ai-imports/batch/apply', ApplyBulkMenuAiImportController::class)
        ->middleware('throttle:10,1');

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

    /*
        Yinelenen ürün adı tespiti (`docs/96`, Faz 2, core-taxonomy). Hız
        sınırlı: her çağrı gömme sağlayıcısına gider.
    */
    Route::get('/workspaces/{workspace}/menu/duplicate-candidates', ShowDuplicateProductCandidatesController::class)
        ->middleware('throttle:10,1');

    /*
        AI kullanılabilirliği (`docs/97` R9). Hız sınırı YOK — hiçbir
        sağlayıcı çağrısı yapmaz, yalnız yapılandırma/bütçe okur; ekran bunu
        her açılışta sorabilmeli, yoksa eylemi gizleyip gizlemeyeceğini
        bilemez.
    */
    Route::get('/workspaces/{workspace}/ai/availability', ShowAiAvailabilityController::class);

    Route::put('/workspaces/{workspace}/menu-categories/{category}/item-order', ReorderMenuItemsController::class);
    Route::put('/workspaces/{workspace}/menu/{menu}/category-order', ReorderCategoriesController::class);

    /*
        MENÜ DENETİM İZİ (FF-163) — "dün kebabın fiyatını kim değiştirdi?"

        Medya izi kendi modülünde (`routes/api/media.php`) duruyor; bu da
        kendi modülünde duruyor. Ayarlar'ın birleşik izi
        (`routes/api/workspace-audit.php`) ayrı bir dosyada çünkü kaynağı
        tek bir modül DEĞİL; bu uç ise yalnız menüyü okur.

        `{menu}` sayıya sınırlı olduğu için `menu/audits` bir menü kimliği
        sanılmaz (aynı gerekçe `menu/duplicate-candidates` için de yazılı).
        Hız sınırı YOK: hiçbir dış sağlayıcıya gitmez ve sahip bir soru
        peşindeyken sayfalar arasında ileri geri gezinir.
    */
    Route::get('/workspaces/{workspace}/menu/audits', ListMenuAuditsController::class);
});
