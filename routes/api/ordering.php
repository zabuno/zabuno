<?php

declare(strict_types=1);

use App\Http\Controllers\Ordering\ChangeOrderStatusController;
use App\Http\Controllers\Ordering\ListKitchenOrdersController;
use App\Http\Controllers\Ordering\ListOrderHistoryController;
use App\Http\Controllers\Ordering\ListPendingOrdersController;
use App\Http\Controllers\Ordering\ShowOrderingSwitchController;
use App\Http\Controllers\Ordering\UpdateOrderingSwitchController;
use Illuminate\Support\Facades\Route;

/*
    RESTORAN TARAFININ SİPARİŞ YOLLARI — `docs/115` S4/S5/S6 (FF-179).

    Misafirin gönderme ucu burada DEĞİL: o yol karekod belirtecinden masayı
    çözer, oturum açmaz ve `routes/web.php` üzerinden gider. İkisini aynı
    dosyaya koymak, `auth:sanctum` grubunun bir gün misafir yoluna da
    uygulanması demekti — ve o gün masadaki hiç kimse sipariş veremezdi.

    ADRES ŞUBEYE DAYANIR (`.../locations/{location}/orders/...`) ve bu bir
    tercih değil: sipariş bir şubenin işidir, çalışma alanının değil. İki
    şubeli bir işletmede şubesiz bir kuyruk adresi, Kadıköy'ün garsonuna
    Beşiktaş'ın masasını gösterirdi.
*/
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // S4 — garson kuyruğu: bekleyenler, en eski üstte.
    Route::get('/workspaces/{workspace}/locations/{location}/orders/pending', ListPendingOrdersController::class);
    // S5 — mutfak monitörü: yalnız onaylanmış ve sonrası.
    Route::get('/workspaces/{workspace}/locations/{location}/orders/kitchen', ListKitchenOrdersController::class);
    // S6 — geçmiş: silinmez, yalnız okunur.
    Route::get('/workspaces/{workspace}/locations/{location}/orders/history', ListOrderHistoryController::class);

    /*
        Durum değişikliğinin TEK ucu. `{order}` sayıya sınırlı: aynı önekte
        `orders/pending`, `orders/kitchen` ve `orders/history` var ve sınır
        olmasaydı bir gün eklenecek `orders/summary` bir sipariş kimliği
        sanılırdı.
    */
    Route::put('/workspaces/{workspace}/locations/{location}/orders/{order}/status', ChangeOrderStatusController::class)
        ->where('order', '[0-9]+');

    // S6 — şalter. Okumak `order.view`, çevirmek `order.settings` ister.
    Route::get('/workspaces/{workspace}/locations/{location}/ordering', ShowOrderingSwitchController::class);
    Route::put('/workspaces/{workspace}/locations/{location}/ordering', UpdateOrderingSwitchController::class);
});
