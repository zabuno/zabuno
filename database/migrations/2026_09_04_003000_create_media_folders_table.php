<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medya klasörleri — `docs/108` §3 madde 1, kanonik kaynak
 * `docs/reference/media-manager/Medya Yonetimi v2.dc.html`.
 *
 * Bugün elli fotoğraf tek bir düz listede duruyor. Kaynağın sol kenar
 * çubuğu ise sayaçlı bir klasör listesi gösteriyor: "Ürünler 4",
 * "Kampanyalar 2". Bu tablo o listenin karşılığıdır.
 *
 * **Neden `parent_id` var ama derinlik iki seviyede duruyor.** Kaynağın
 * `folderDefs` verisinde yalnız `depth: 0` ve `depth: 1` geçiyor
 * ("Ürünler" → "Tatlılar"), süzgeç de `f.folder === s.folder` ile yalnız
 * DOĞRUDAN dosyaya bakıyor — alt klasörün dosyalarını üste toplamıyor.
 * Sütun bu yüzden genel bir ağaç kurabilecek şekilde duruyor (ileride
 * gerekirse kapı açık kalsın), ama uygulama katmanı üçüncü seviyeyi
 * reddediyor. Sınırsız derinlik, kaynağın hiç göstermediği bir yetenek
 * için özyinelemeli sayım, döngü kontrolü ve taşıma kuralları getirirdi;
 * ödediği bedelin karşılığı ekranda görünmüyor.
 *
 * `position` sıralamayı sahibin eline verir: klasör listesi kimlik sırasına
 * göre değil, sahibin koyduğu sıraya göre okunur. Bugün otomatik artıyor.
 *
 * Silme CASCADE DEĞİLDİR: `media_assets.media_folder_id` `nullOnDelete`
 * ile tanımlıdır (bkz. eşlik eden göç). Klasör silmek fotoğraf silmez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_folders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            /*
                Üst klasör silinirse alt klasör de gider — ama uygulama
                katmanı alt klasörü olan bir klasörün silinmesini zaten
                reddediyor (409). Buradaki cascade, workspace silinmesi
                gibi gerçekten toplu bir temizlikte yetim satır kalmasın
                diye vardır, gündelik silme yolu değildir.
            */
            $table->foreignId('parent_id')->nullable()->constrained('media_folders')->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            // Kenar çubuğu her açılışta bu sırayla okunuyor.
            $table->index(['workspace_id', 'parent_id', 'position']);
            /*
                Aynı üst klasör altında aynı ad iki kez olamaz: sahip kendi
                "Kampanyalar"ını diğer "Kampanyalar"dan ayırt edemezdi.
                Kısıt kiracıya bağlıdır — iki farklı restoranın aynı adı
                kullanması gayet normaldir. Kök klasörlerde `parent_id`
                NULL olduğu için bu indeks tek başına yetmez (SQL'de NULL
                kendine eşit değildir) — asıl kapı uygulama katmanındaki
                ad kontrolüdür; indeks yarışan iki isteğe karşı ikinci
                savunma hattıdır.
            */
            $table->unique(['workspace_id', 'parent_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_folders');
    }
};
