<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SİPARİŞ ALMANIN İKİ ŞARTI — `docs/115` §5, `docs/114` §3 Dalga 6 (FF-176).
 *
 * İki sütun, iki farklı soru; ve bilerek FARKLI yerlerde yaşıyorlar.
 *
 * 1. `locations.accepts_orders` — "bu şube ŞU AN sipariş alıyor mu?"
 *    CANLI okunur. Sahip gece 23:00'te sipariş almayı kapattığında karar
 *    ANINDA geçerli olmalı: kapalıyken gelen bir sipariş, kimsenin
 *    bakmadığı bir kuyruğa düşerdi. Yayına dondurulsaydı, sahibin
 *    kapattığı bir hizmet yeni bir yayına kadar açık kalırdı.
 *
 *    VARSAYILAN KAPALI. Sipariş alma, panelde birinin BAKMASINI gerektiren
 *    tek yetenektir; kendiliğinden açılsaydı, güncelledikten sonra hiçbir
 *    şey yapmayan bir restoranın mutfağına sessizce iş düşerdi. Sahip
 *    açmadan hiçbir sipariş alınmaz.
 *
 * 2. `menu_publications.entitlements` — "bu yayın HANGİ PLANLA yapıldı?"
 *    DONDURULUR. Sahip planını düşürdüğünde masadaki basılı karekod aynı
 *    kâğıttır ve o kâğıdın gösterdiği yayın değişmemelidir; ödeme gecikmesi
 *    masada oturan misafirin ekranını ortasından kesmez. Plan değişikliği
 *    BİR SONRAKİ yayında etkisini gösterir.
 *
 *    `snapshot` sütununun İÇİNE yazılmadı ve bu bilinçli: `snapshot` misafire
 *    çizilen MENÜ İÇERİĞİDİR ve yapılandırılmış veriye kadar oradan türer.
 *    Plan, menü içeriği değildir — yayının yapıldığı KOŞULDUR. İkisini aynı
 *    kaba koymak, bir plan değişikliğini bir gün "menü değişti" gibi
 *    gösterirdi.
 *
 *    NULLABLE ve öyle KALACAK: bu göçten önce yapılmış yayınların planı
 *    bilinmiyor ve geriye dönük bir değer uydurmak, o gün geçerli olmayan
 *    bir hakkı varmış gibi göstermek olurdu. Okuma tarafı `null` gördüğünde
 *    canlı plana düşer ve bunu açıkça yapar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->boolean('accepts_orders')->default(false)->after('timezone');
        });

        Schema::table('menu_publications', function (Blueprint $table): void {
            $table->json('entitlements')->nullable()->after('snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('menu_publications', function (Blueprint $table): void {
            $table->dropColumn('entitlements');
        });

        Schema::table('locations', function (Blueprint $table): void {
            $table->dropColumn('accepts_orders');
        });
    }
};
