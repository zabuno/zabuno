<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ŞUBENİN ÇALIŞMA SAATLERİ (`docs/109` §6.4; kaynak `panel.dc.html`,
 * "Şubeler" ekranının üçüncü sayısı: `09:00–23:00`).
 *
 * NEDEN AYRI TABLO, NEDEN GÜN GÜN
 * -------------------------------
 * `locations` üstünde iki sütun (`opens_at` / `closes_at`) daha ucuz
 * görünürdü ve kaynağın kartı da tek aralık gösteriyor. Ama kart bir
 * SUNUMDUR, verinin şekli değil. Gerçek bir restoranın haftası tek aralık
 * değildir: pazartesi kapalıdır, cuma gece ikiye kadar açıktır. İki sütun,
 * o restorana ya yalan söyletir ya hiç girdirtmez — ve o yalan yalnız
 * ekranda kalmaz: "şu anda açık mıyız" sorusunun cevabı da onunla bozulur.
 *
 * Ters yön hiçbir şey kaybettirmez: hafta tek tipse kart yine tek aralık
 * gösterir. Bu yüzden veri gün gün, sunum özet.
 *
 * DAKİKA, SAAT DEĞİL
 * ------------------
 * Saat, günün başlangıcından itibaren DAKİKA olarak saklanır (09:00 → 540).
 * `time` sütunu yerine tam sayı seçilmesinin sebebi gece yarısıdır:
 * "10:00–00:00" bir `time` sütununda `10:00`–`00:00` olur ve kapanış
 * açılıştan ÖNCE görünür; "18:00–02:00" ise iki güne bölünmek zorunda
 * kalırdı. Dakikada kapanış 1440'ı aşabilir — 600→1440 ve 1080→1560 — yani
 * gece yarısı aşımı bir istisna değil, ölçünün doğal devamıdır.
 *
 * Aynı birim menü servis aralıklarında zaten kullanılıyor
 * (`MenuSchedulePort`, `docs/109` §7.1): depo tek bir saat dili konuşur.
 *
 * KAPALI GÜN İLE GİRİLMEMİŞ GÜN AYRIDIR
 * -------------------------------------
 * `is_closed = true` bir olgudur ("pazartesi kapalıyız"). Şubenin HİÇ
 * satırı olmaması ise bir bilgisizliktir ("henüz girmedim"). İkisi aynı
 * cevabı hak etmez: birincisi misafire söylenebilir, ikincisi
 * söylenemez ve kartta o satır hiç çizilmez.
 *
 * SAAT DİLİMİ BURADA YOK, ÇÜNKÜ ŞUBEDE VAR
 * ----------------------------------------
 * `locations.timezone` (`docs/62`) zaten şubenin kendi IANA kimliğini
 * taşıyor. Saatler ona göre okunur; buraya kopyalanması iki kaynak
 * yaratır ve biri güncellenmeyi unutur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_opening_hours', function (Blueprint $table): void {
            $table->id();

            // Kiracı kimliği satırın ÜSTÜNDE durur. Şubeden türetilebilirdi;
            // ama `dining_tables` dahil her kiracı tablosu onu taşıyor ve
            // sızıntıyı tek bir `where` ile kapatabilmek, her sorguda bir
            // birleştirme hatırlamaktan güvenlidir.
            $table->foreignId('workspace_id')->constrained('workspaces');

            // Şube silinirse saatleri de gider: sahibi olmayan bir çalışma
            // saati hiçbir soruyu cevaplamaz.
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();

            // ISO-8601: 1 = Pazartesi … 7 = Pazar. Sıfır tabanlı ve pazarla
            // başlayan diziler ülkeye göre değişir; ISO değişmez.
            $table->unsignedTinyInteger('day_of_week');

            $table->boolean('is_closed')->default(false);

            // Açılış her zaman GÜNÜN İÇİNDEDİR (0–1439); kapanış gece
            // yarısını aşabilir (1–2880). Kapalı günde ikisi de boştur.
            $table->unsignedSmallInteger('opens_minute')->nullable();
            $table->unsignedSmallInteger('closes_minute')->nullable();

            $table->timestamps();

            // Bir günün tek bir cevabı vardır. Aynı gün için ikinci bir satır,
            // ekranda hangisinin doğru olduğunu belirsiz bırakırdı.
            $table->unique(['location_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_opening_hours');
    }
};
