<?php

declare(strict_types=1);

use App\Http\Controllers\Rating\DeleteRatingReplyController;
use App\Http\Controllers\Rating\ListMenuRatingsController;
use App\Http\Controllers\Rating\UpdateRatingReplyController;
use Illuminate\Support\Facades\Route;

/*
    RESTORAN TARAFININ PUAN YOLLARI — `docs/116` P5/P6.

    Misafirin oy verme ucu burada DEĞİL: o yol karekod belirtecinden masayı
    çözer, oturum açmaz ve `routes/web.php` üzerinden gider. Sipariş
    yollarında aynı ayrım aynı gerekçeyle yapıldı — ikisini aynı dosyaya
    koymak, `auth:sanctum` grubunun bir gün misafir yoluna da uygulanması
    demekti.

    ═══ BU DOSYADA PUANI SİLEN YA DA DEĞİŞTİREN BİR YOL YOKTUR ═══

    Eksiklik değil, `docs/116` §4'ün kendisi: *"Sahip puanı silemez. Yanıt
    verebilir, kaldıramaz — silebiliyorsa ortalama bir pazarlama sayısıdır."*
    `/reply` altındaki PUT ve DELETE sahibin KENDİ cümlesine dokunur, hiç
    kimsenin ölçümüne değil. `OwnerRatingReplyTest` bu ayrımı yol düzeyinde
    donduruyor.

    OKUMA ADRESİ MENÜYE DAYANIR, YANIT ADRESİ ÜRÜNE. Bu bir tutarsızlık
    değil: sahip puanları MENÜSÜNÜ okurken görür (hangi tabak nerede
    duruyor), ama yanıtı TABAĞA yazar — aynı tabak iki menüde birden
    olabilir ve restoranın onun hakkında söylediği söz tektir.
*/
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/workspaces/{workspace}/menus/{menu}/ratings', ListMenuRatingsController::class)
        ->where('workspace', '[0-9]+')
        ->where('menu', '[0-9]+');

    Route::put('/workspaces/{workspace}/ratings/products/{product}/reply', UpdateRatingReplyController::class)
        ->where('workspace', '[0-9]+')
        ->where('product', '[0-9]+');

    Route::delete('/workspaces/{workspace}/ratings/products/{product}/reply', DeleteRatingReplyController::class)
        ->where('workspace', '[0-9]+')
        ->where('product', '[0-9]+');
});
