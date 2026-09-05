<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SİPARİŞ — `docs/115` §7 S1 (FF-176).
 *
 * SEPET BURAYA YAZILMAZ. Sepet cihazda yaşar; sunucuya yalnız GÖNDERİLEN
 * sipariş düşer. Sepeti sunucuda tutmak, hiç sipariş vermeyecek her misafir
 * için satır yazmak olurdu — ve o satırların ne zaman silineceği hiç
 * cevaplanmayan bir soruya dönüşürdü.
 *
 * AD, FİYAT VE ALERJEN SATIRIN İÇİNE KOPYALANIR, ÜRÜNE BAĞLANMAZ.
 *
 * Bu, bu depodaki yayın anlık görüntüsü deseninin aynısıdır ve aynı sebeple
 * vardır: yarın fiyat değiştiğinde dünkü sipariş değişmemeli. Mutfak
 * monitöründe görünmesi gereken şey, siparişin verildiği ANDAKİ gerçektir —
 * `menu_items`'a bağlansaydı, sahip akşam bir ürünü yeniden adlandırdığında
 * o günün bütün fişleri sessizce yeni adı gösterirdi. Alerjen için aynı
 * hata bir sağlık olayıdır.
 *
 * `menu_item_id` yine de TUTULUR ama yalnız izlenebilirlik içindir
 * ("hangi satır ne kadar sipariş edildi"); satırın gösterdiği metin ondan
 * OKUNMAZ. Menü satırı silindiğinde `null` olur ve sipariş bozulmaz.
 *
 * SİPARİŞ SİLİNMEZ (`docs/115` Y2): geçmiş bir denetim izi gibi kalıcıdır.
 * Bu yüzden burada hiçbir `cascadeOnDelete` bir siparişi düşürmez; yalnız
 * siparişin KENDİ satırları siparişe bağlıdır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();

            /*
                KİRACI VE ŞUBE SİPARİŞİN ÜSTÜNDE DURUR, MENÜNÜN ARKASINDA
                DEĞİL. Sorgunun `WHERE`'i bu iki sütunu okur; menü üzerinden
                çıkarım yapmak, bir gün menü taşındığında sınırı sessizce
                kaydırırdı.
            */
            $table->foreignId('workspace_id')->constrained('workspaces');
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('menu_id')->constrained('menus');

            /*
                MASA ZORUNLUDUR. Masasız bir sipariş, garsonun nereye
                götüreceğini bilmediği bir siparistir; onu kabul edip sonra
                panelde "bilinmiyor" yazmak, misafire yalan söylemenin yavaş
                biçimidir. Masaya bağlı olmayan karekod (giriş kodu) sipariş
                yolunda dürüstçe reddedilir.
            */
            $table->foreignId('dining_table_id')->constrained('dining_tables');

            // Hangi kod tarandı ve hangi yayın gösteriliyordu: ikisi de
            // sonradan cevaplanamayan sorulardır. Kod ya da yayın silinirse
            // sipariş yaşamaya devam eder.
            $table->foreignId('qr_code_id')->nullable()->constrained('qr_codes')->nullOnDelete();
            $table->foreignId('publication_id')->nullable()->constrained('menu_publications')->nullOnDelete();

            /*
                Durum ENUM'un değerini taşır ve genişliği ölçülüdür:
                PostgreSQL `varchar(n)` sınırını UYGULAR, SQLite hiç
                uygulamaz. En uzun değer dokuz karakter; 20 hem bugünü hem
                makul bir yarını taşır ve `OrderStatusMachineTest` bunu
                göçten önce kırar.
            */
            $table->string('status', 20);

            /*
                ZİYARETÇİ ANAHTARI BİR KİMLİK DEĞİLDİR (`docs/115` §1).
                `docs/68` deseniyle türetilir: ham IP/tarayıcı saklanmaz,
                tuz her gün döner. Tek işi, AYNI CİHAZIN kendi siparişini
                kendi ekranında görebilmesidir. Tuz döndüğü için bu bağ
                ertesi gün kopar — ve bu bir kusur değil, ödenen bedeldir:
                kalıcı yapmak, takibi kalıcı yapmak demekti.
            */
            $table->string('visitor_key', 64);

            // Toplam SİPARİŞ ANINDA donar. Satırlardan yeniden hesaplanan
            // bir toplam, satır kopyaları değişmese bile bir gün yuvarlama
            // kuralı değiştiğinde ayrışırdı.
            $table->unsignedBigInteger('total_minor_amount');
            $table->string('currency_code', 3);

            /*
                RET SEBEBİ (`docs/115` G3): misafirin ekranında görünür.
                280 karakter, garsonun servis anında yazacağı bir cümlenin
                üstünde ve sütunu PG'de taşırmayacak kadar dar.
            */
            $table->string('rejection_reason', 280)->nullable();

            /*
                ZAMAN DAMGALARI — HER BİRİ BİR SORUYA BAĞLI, ve her biri BİR
                KEZ yazılır. Ayrı bir geçiş günlüğü tablosu açmadık: her
                aşamanın kendi sütunu zaten değişmez bir kayıttır ve tek
                satır okuyarak "ne zaman ne oldu" cevabını verir. İkinci bir
                tablo, mutfak monitörünün her yenilemesine bir birleştirme
                eklerdi.

                `placed_at`      — kuyruk sırası; en eski üstte (G1).
                `confirmed_at`   — mutfağa düşme anı; monitörün beklemesi.
                `preparing_at`   — ocağın başladığı an.
                `ready_at`       — servise hazır olma anı.
                `closed_at`      — akışın bittiği an. TESLİM, İPTAL ve RET
                                   aynı sütunu yazar; hangisi olduğunu
                                   `status` söyler. Üç ayrı sütun, aynı
                                   soruyu üç kez sormak olurdu.
                `status_changed_at` — son hareket. Mutfak monitöründe donmuş
                                   bir ekranla dolu bir ekran aynı görünür
                                   (`docs/115` §6); ekran bu anı yazar.
            */
            $table->timestamp('placed_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('preparing_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('status_changed_at');

            $table->timestamps();

            // Garson kuyruğu: şubenin bekleyenleri, en eski üstte.
            $table->index(['workspace_id', 'location_id', 'status', 'placed_at'], 'orders_service_queue_index');
            // Masa başına açık sipariş sayımı — hız sınırının ikinci katmanı.
            $table->index(['dining_table_id', 'status'], 'orders_open_per_table_index');
            // Misafirin kendi siparişi: aynı cihaz, son gönderdikleri.
            $table->index(['visitor_key', 'placed_at'], 'orders_visitor_index');
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();

            // Satır siparişe aittir ve onsuz anlamsızdır; siparişin kendisi
            // hiç silinmez, dolayısıyla bu zincir pratikte hiç yürümez.
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            // Yalnız izlenebilirlik: satırın METNİ buradan okunmaz.
            $table->foreignId('menu_item_id')->nullable()->constrained('menu_items')->nullOnDelete();

            /*
                KOPYALANAN ALANLAR. Genişlik `products.name` ile AYNI (255):
                kataloğun kabul ettiği bir adı sipariş satırı kırpsaydı,
                mutfaktaki fiş menüdekinden farklı bir ürün gösterirdi — ve
                PostgreSQL bunu kırpmaz, isteği reddeder.
            */
            $table->string('product_name', 255);
            $table->unsignedBigInteger('unit_price_minor_amount');
            $table->string('currency_code', 3);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total_minor_amount');

            /*
                ALERJEN KOPYASI (`docs/115` K4). Ürünün alerjen listesi
                sipariş anında dondurulur. Bu, bu göçteki en yüksek bedelli
                karardır: yanlış bir alerjen bilgisi bir sağlık olayıdır ve
                "ürün sonradan düzenlendi" bir savunma değildir.
            */
            $table->json('allergens');

            $table->timestamps();

            $table->index('order_id', 'order_items_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
