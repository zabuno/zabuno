<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ÇOKLU MENÜ VE SAAT BAZLI GEÇİŞ — sahibin 2026-09-05 kararı,
 * `docs/109-PANEL-V3.md` §7.1.
 *
 * Sahibe açıkça soruldu ve "çoklu menü YAPILSIN, saat bazlı geçişli" dedi.
 * Bugüne kadarki `menus.location_id` UNIQUE kısıtı bir kusur değildi; o
 * günün ÜRÜN KAPSAMIYDI. Kapsam sahibi tarafından genişletildi, bu yüzden
 * kısıt gevşetiliyor.
 *
 * ARALIK MODELİ VE GEREKÇESİ — neden (başlangıç, bitiş) çifti DEĞİL
 *
 * Sahibin kuralı katı: *"Aralıklar ÇAKIŞAMAZ ve boşluk bırakılamaz: gün 24
 * saattir ve hiçbir saatte 'hangi menü' sorusu cevapsız kalamaz."*
 *
 * Her menüye bir (başlangıç, bitiş) çifti verseydik bu kural bir DOĞRULAMA
 * olurdu ve her yazma yolunda yeniden çalıştırılmak zorunda kalırdı: menü
 * ekleme, saat düzenleme, devre dışı bırakma, silme, CSV aktarımı, yarın
 * eklenecek her yeni yol. Bir yol doğrulamayı atladığında kimse fark etmez;
 * fark eden, sabah 07:05'te boş bir sayfaya bakan misafir olur.
 *
 * Bu yüzden gün, ARALIKLARLA değil GEÇİŞ ANLARIYLA saklanır. Her satır
 * "şu dakikadan itibaren şu menü" der. Bir andaki menü, o andan önceki EN
 * SON geçiştir ve günün başındaki an günün SON geçişine bağlanır. Sonuçlar:
 *
 * - ÇAKIŞMA veritabanı düzeyinde imkânsız: `unique(location_id,
 *   start_minute)` bir dakikaya iki menü koyulmasını reddeder ve bir an
 *   her zaman tek bir geçişe düşer.
 * - BOŞLUK yapısal olarak imkânsız: geçişler günü döngüsel olarak böler.
 *   Geçiş eklemek bir aralığı ikiye böler, silmek iki aralığı birleştirir;
 *   ikisi de kapsamayı bozamaz.
 * - "22:00–02:00" gibi gece yarısını aşan bir aralık ÖZEL DURUM DEĞİLDİR:
 *   22:00'de "Gece", 02:00'de "Ana menü" diye iki geçiş yazılır ve döngü
 *   gerisini halleder.
 * - Hiç geçiş yoksa gün bütünüyle şubenin ÇIPA menüsüne aittir. Bugünkü
 *   tek menülü şubelerin davranışı budur; hiçbir şey değişmez.
 *
 * DAKİKA, saatin değil GÜNÜN dakikasıdır (0–1439) ve ŞUBENİN saat
 * diliminde okunur (`locations.timezone`). Sunucunun saati veya sabit bir
 * `Europe/Istanbul`, Berlin'deki bir şubenin kahvaltısını bir saat geç
 * açardı.
 *
 * GERİYE UYUM: var olan her menü "tüm günü kaplayan varsayılan menü" olur —
 * `state='active'` ve 0. dakikada tek bir geçiş. Tek menülü bir şube
 * bugünkü gibi çalışmaya devam eder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            /*
                Yabancı anahtar sütununun indekssiz kalmaması için ÖNCE
                indeks eklenir, SONRA benzersizlik düşürülür. Ters sırada
                MySQL, `location_id` üzerindeki FK'yi indeksiz bıraktığı
                için `dropUnique`'i reddeder.
            */
            $table->index('location_id', 'menus_location_id_index');
        });

        Schema::table('menus', function (Blueprint $table) {
            $table->dropUnique('menus_location_id_unique');
        });

        Schema::table('menus', function (Blueprint $table) {
            // Menü haplarının ekrandaki sırası. Kimliğe göre sıralamak,
            // sahibin sırayı değiştirmesini imkânsız kılardı.
            $table->unsignedInteger('sort_order')->default(0)->after('state');
        });

        Schema::create('menu_service_switches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();

            // Günün dakikası: 0 = 00:00, 420 = 07:00, 1439 = 23:59.
            $table->unsignedSmallInteger('start_minute');
            $table->timestamps();

            /*
                KAPSAMA KURALININ TAŞIYICISI. Aynı şubede aynı dakikaya iki
                geçiş yazılamaz; bu yüzden hiçbir an iki menüye ait olamaz.
                Çakışma bir doğrulama değil, kurulamayan bir hâldir.
            */
            $table->unique(['location_id', 'start_minute']);
            $table->index('menu_id');
        });

        /*
            GERİYE UYUM. Var olan her menü tüm günü kaplayan varsayılan menü
            olur: tek bir geçiş, 0. dakika. Bir şubede (kuramsal olarak) tek
            menü olduğu için çakışma doğmaz.
        */
        foreach (DB::table('menus')->orderBy('id')->get(['id', 'location_id']) as $menu) {
            DB::table('menu_service_switches')->insert([
                'location_id' => $menu->location_id,
                'menu_id' => $menu->id,
                'start_minute' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menus')->update(['state' => 'active', 'sort_order' => 0]);
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_service_switches');

        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });

        /*
            Benzersizlik geri konurken şube başına fazladan menüler SİLİNMEZ.
            Bir göç geri alması sahibin verisini yok edemez; kısıt geri
            konamazsa göç patlar ve bu doğru davranıştır — sessiz veri kaybı
            değil, görünür bir hata.
        */
        Schema::table('menus', function (Blueprint $table) {
            $table->unique('location_id', 'menus_location_id_unique');
        });

        Schema::table('menus', function (Blueprint $table) {
            $table->dropIndex('menus_location_id_index');
        });
    }
};
