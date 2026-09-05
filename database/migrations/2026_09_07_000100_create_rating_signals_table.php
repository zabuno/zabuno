<?php

declare(strict_types=1);

use App\Domain\Rating\RatingSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HAM PUAN SİNYALİ — `docs/116` §1 (FF-180 / P1).
 *
 * ═══ PUAN BİR SÜTUN DEĞİL, BİR ÇIKTIDIR ═══
 *
 * "Beş yıldızın ortalaması" bir hesap değil, bir VARSAYIMDIR: her oyun eşit
 * ağırlıkta olduğunu, zamanın önemsiz olduğunu ve kaynağın fark etmediğini
 * varsayar. Sahibin kararı bu varsayımı reddediyor — dolayısıyla ortalama
 * hiçbir ürün ya da menü satırına YAZILMAZ. `menu_items.rating_average`
 * diye bir sütun yoktur ve olmayacaktır.
 *
 * Bu ayrım şimdi yapılmazsa sonradan yapılamaz: ortalamayı satır üstüne
 * yazan bir sistem ham sinyalleri saklamayı gereksiz görür ve o sinyaller
 * bir daha geri gelmez.
 *
 * ═══ BU TABLO DEĞİŞMEZ (append-only) ═══
 *
 * Satır yazılır, güncellenmez, silinmez. Tek istisna işaretlemedir
 * (`excluded_at`) — ve o bile bir silme değil, bir nottur.
 *
 * ═══ NEDEN `workspace_id` SERT ANAHTARLA BAĞLI DEĞİL ═══
 *
 * Bu depoda alışılmış olan `constrained('workspaces')`tir ve burada bilerek
 * kullanılmadı. Sebep bu deponun kendi geçmişi: `analytics_events` sert
 * anahtarla kurulmuştu ve menü silme akışı doğduğunda göçle GEVŞETİLMEK
 * zorunda kaldı (`2026_09_05_000500`), çünkü sert anahtarın iki ucu da
 * kabul edilemezdi — `cascade` ölçüm geçmişini silerdi, `restrict` ise
 * sahibin kendi verisini silmesini imkânsız kılardı. Değişmez bir ölçüm
 * defteri, işaret ettiği satırlardan DAHA UZUN yaşamak zorundadır.
 *
 * Kiracı sınırı burada yazma yolunda ve her okumadaki `WHERE workspace_id`
 * ile korunur; aynı sebeple `subject_type`/`subject_id` de zaten hiçbir
 * zaman sert anahtar taşıyamaz (çok biçimli bir işaret bunu kaldırmaz).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_signals', function (Blueprint $table): void {
            $table->id();

            // Kiracı: her okumanın `WHERE`'i bu sütunu görür.
            $table->unsignedBigInteger('workspace_id');

            /*
                NEYE PUAN VERİLDİ — çok biçimli, çünkü yarın şubeye ve
                menüye de puan verilecek. Bugün tek değer `product`.
                Ayrı tablolar açmak (product_ratings, location_ratings)
                aynı algoritmayı iki yerde çalıştırmak olurdu ve ikisi bir
                gün ayrışırdı.

                32 karakter: PostgreSQL `varchar(n)`'i UYGULAR, SQLite hiç
                uygulamaz — yani taşan bir değer yerelde geçer, dağıtım
                motorunda reddedilir.
            */
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');

            /*
                Ö1 — HER SİNYAL KAYNAĞINI TAŞIR ve bu sütun BOŞ BIRAKILAMAZ.

                Boş bırakılabilseydi, ikinci kaynak geldiğinde eski
                satırların hepsi "kaynağı bilinmiyor" olurdu ve
                ağırlıklandırma o günden öncesini kapsayamazdı. Geriye
                dönük doldurmanın her cevabı bir uydurmadır.
            */
            $table->string('source', RatingSource::MAX_VALUE_LENGTH);

            /*
                PUAN VE ÖLÇEĞİ BİRLİKTE YAŞAR.

                Zomato beş üzerinden verir, bir sosyal uygulama
                beğen/beğenme (1 üzerinden) olabilir. Ölçeği yazmadan ham
                puanı saklamak, iki farklı birimdeki sayıyı aynı sütuna
                koymaktır — ve o karışıklık sonradan çözülemez, çünkü hangi
                satırın hangi birimde olduğunu söyleyecek hiçbir kayıt
                kalmaz.
            */
            $table->unsignedSmallInteger('score_value');
            $table->unsignedSmallInteger('score_scale_max');

            /*
                ZİYARETÇİ ANAHTARI BİR KİMLİK DEĞİLDİR (`docs/68` deseni,
                `orders` ile aynı): ham IP/tarayıcı saklanmaz, tuz döner.
                Tek işi "aynı cihaz aynı ürüne ikinci kez oy vermesin"
                kuralını kurmaktır.

                DIŞ KAYNAKTAN GELEN SİNYALDE ZİYARETÇİ YOKTUR: Google'daki
                bir yorumcunun bizde bir anahtarı olamaz. Bu yüzden sütun
                boş bırakılabilir. Zorunlu yapmak, dış satırlara sahte bir
                anahtar uydurmayı gerektirirdi — ve o sahte anahtarlar bir
                gün gerçek sanılırdı.
            */
            $table->string('visitor_key', 64)->nullable();

            /*
                MASADAN GELDİĞİNİN KANITI.

                `docs/116` §4: oy vermek için o masadan karekod okutmuş
                olmak gerekir. Bağlam sinyalde saklanmazsa bu iddia
                sonradan doğrulanamaz — ve doğrulanamayan bir iddia bir
                pazarlama cümlesidir.

                Karekod ya da masa silinirse sinyal YAŞAMAYA DEVAM EDER
                (`nullOnDelete`): sahibin masa düzenini değiştirmesi geçmiş
                ölçümü yok edemez. Kanıt bağı kopar, oyun kendisi kalır.

                Dış kaynaklı sinyalde ikisi de boştur ve bu doğrudur:
                o oy masadan gelmedi.
            */
            $table->foreignId('qr_code_id')->nullable()->constrained('qr_codes')->nullOnDelete();
            $table->foreignId('dining_table_id')->nullable()->constrained('dining_tables')->nullOnDelete();

            /*
                İKİ AYRI ZAMAN, İKİ AYRI SORU.

                `observed_at` — oyun VERİLDİĞİ an. Sönüm bunu okur.
                `recorded_at` — bizim ONU GÖRDÜĞÜMÜZ an.

                Dış kaynakta ikisi aylarca ayrışır: Zomato'daki bir yorum
                geçen yıl yazılmış olabilir ama biz onu bugün çekeriz. Tek
                sütun olsaydı, ya bir yıllık bir yorumu bugünkü kadar taze
                sayardık ya da bugün kurduğumuz bağlantıyı bir yıllık
                gösterirdik. İkisi de yanlış.
            */
            $table->timestamp('observed_at');
            $table->timestamp('recorded_at');

            /*
                §4 — KÖTÜYE KULLANIM SİLMEZ, İŞARETLER.

                Algoritma işaretli sinyali ağırlıklandırmada dışarıda
                bırakır; satır okunabilir kalır. Silmek, yanlış
                işaretlemenin geri dönüşünü de silerdi: "bu oyu neden
                saymadık?" sorusunun cevabı, o oyun kendisidir.

                64 karakter, `config/rating-algorithm/v1.php`'deki kapalı
                sebep listesinin en uzun değerini rahat taşır.
            */
            $table->timestamp('excluded_at')->nullable();
            $table->string('exclusion_reason', 64)->nullable();

            $table->timestamps();

            // Bir ürünün sinyalleri, en yenisi önce: yeniden hesaplamanın
            // ve ekrandaki kırılımın okuduğu sıra.
            $table->index(
                ['workspace_id', 'subject_type', 'subject_id', 'observed_at'],
                'rating_signals_subject_index'
            );
            // Kaynak kırılımı: "bu puanın ne kadarı masadan geldi?"
            // (`docs/116` §5 D1 — dış puan bizim puanımız gibi gösterilmez).
            $table->index(['workspace_id', 'source', 'observed_at'], 'rating_signals_source_index');
            // Kötüye kullanım incelemesi: aynı cihazın son hareketleri.
            $table->index(['visitor_key', 'observed_at'], 'rating_signals_visitor_index');

            /*
                "ZİYARETÇİ + ÜRÜN BAŞINA TEK OY" BURADA BİR BENZERSİZLİK
                KISITI DEĞİL, ÇÜNKÜ HENÜZ CEVAPLANMAMIŞ BİR SORU VAR:
                misafir fikrini değiştirirse ne olur? Değişmez bir defterde
                cevap "yeni bir satır" olmak zorundadır ve o satır
                benzersizlik kısıtını ihlal ederdi. Kural P4'te, oy verme
                ucuyla birlikte ve o soruya verilmiş açık bir cevapla
                konur; bugün konsaydı, cevabı düşünmeden dondurmuş olurduk.
            */
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_signals');
    }
};
