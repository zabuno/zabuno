<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TÜRETİLMİŞ PUAN — `docs/116` §1 Ö2/Ö3 (FF-180 / P3).
 *
 * ═══ BU TABLONUN TAMAMI SİLİNSE HİÇBİR ŞEY KAYBOLMAZ ═══
 *
 * Buradaki her satır `rating_signals`'tan yeniden üretilebilir; testi de
 * budur. Bir gün bir satır yanlışsa çare onu elle düzeltmek değil,
 * `php artisan rating:recompute` çalıştırmaktır. Elle düzeltilebilen bir
 * türetilmiş puan, türetilmiş değildir.
 *
 * ═══ Ö3 — SÜRÜM DAMGASI ZORUNLUDUR ═══
 *
 * `algorithm_version` boş bırakılabilseydi, "bu ürünün puanı neden düştü?"
 * sorusunun cevabı olmazdı: kural mı değişti, oy mu geldi, ayırt edilemez.
 *
 * Damga aynı zamanda GERİYE DÖNÜK DÜRÜSTLÜĞÜN aracıdır. Eski bir puanı yeni
 * kuralla yeniden hesaplayıp "hep böyleydi" demek, geçmişi yeniden
 * yazmaktır. Bu yüzden benzersizlik kısıtı sürümü de İÇERİR: v1 ile
 * hesaplanmış satır, v2 hesaplandığında ÜZERİNE YAZILMAZ — yan yana
 * dururlar ve iki sürüm karşılaştırılabilir. Sürüm yükseltmenin bir paket
 * olmasının (`docs/116` §2) veri tarafındaki karşılığı budur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_scores', function (Blueprint $table): void {
            $table->id();

            // `rating_signals` ile aynı sınır ve aynı çok biçimli işaret;
            // aynı sebeplerle sert anahtar taşımaz (bkz. 000100 göçü).
            $table->unsignedBigInteger('workspace_id');
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');

            /*
                HANGİ KURALLA HESAPLANDI. Boş bırakılamaz.

                `unsignedSmallInteger`: sürüm numarası bir sayaçtır, yılda
                birkaç kez artar; 65 bin sürüm, bu ürünün ömründen uzundur.
            */
            $table->unsignedSmallInteger('algorithm_version');

            /*
                PUANIN KENDİSİ VE ÖLÇEĞİ.

                `decimal(6,4)`, kayan noktalı sayı DEĞİL: ondalık gösterim
                aynı girdiden aynı çıktıyı verir. `float` olsaydı aynı
                sinyallerden yeniden hesaplanan puan son basamakta oynardı
                ve "puan değişti mi?" sorusu gürültüye boğulurdu.

                Ölçek burada da saklanır (`rating_signals` ile aynı sebep):
                algoritma dosyası yarın on üzerinden gösterime geçse, eski
                satırlar hâlâ beş üzerinden olduklarını SÖYLEYEBİLİR.
            */
            $table->decimal('score_value', 6, 4);
            $table->unsignedSmallInteger('score_scale_max');

            /*
                EŞİK KARARININ GİRDİLERİ SAKLANIR (`docs/116` §3).

                `signal_count` — kaç sinyal katıldı.
                `total_weight` — sönüm ve kaynak ağırlığı sonrası toplam.

                İkisi de saklanmasaydı, "bu ürünün puanı neden görünmüyor?"
                sorusuna ancak bütün sinyalleri yeniden tarayarak cevap
                verilebilirdi. Sahibin ekranında görünmesi gereken cümle
                ("henüz yeterli değerlendirme yok") bu iki sayıya dayanır.
            */
            $table->unsignedInteger('signal_count');
            $table->decimal('total_weight', 12, 6);

            /*
                EŞİK GEÇİLDİ Mİ — kararın kendisi, girdileriyle birlikte
                saklanır. Ekranın bunu yeniden hesaplaması gerekseydi, eşik
                kuralı bir de gösterim katmanında yaşardı; iki yerde yaşayan
                bir kural bir gün iki farklı cevap verir.

                SIFIR YILDIZ DİYE BİR ŞEY YOK: eşik geçilmediyse ekran
                "henüz yeterli değerlendirme yok" der. Sıfır bir ÖLÇÜMDÜR ve
                bilinmeyenin yerine geçemez.
            */
            $table->boolean('meets_display_threshold');

            // Yeniden hesaplamanın çalıştığı an: "bu sayı ne kadar eski?"
            $table->timestamp('computed_at');

            $table->timestamps();

            /*
                BİR ÜRÜN + BİR SÜRÜM = BİR SATIR.

                Sürüm anahtarın parçasıdır: yeni sürüm eskisini ezmez,
                yanına yazılır. Eskiyi ezseydik, sürüm damgasının
                (`Ö3`) tek işi olan "geçmiş yeniden yazılmasın" korumasını
                veri tarafında iptal etmiş olurduk.
            */
            $table->unique(
                ['workspace_id', 'subject_type', 'subject_id', 'algorithm_version'],
                'rating_scores_subject_version_unique'
            );

            // Menü ekranının okuduğu sıra: bir kiracının bir sürümdeki
            // gösterilebilir puanları.
            $table->index(
                ['workspace_id', 'algorithm_version', 'meets_display_threshold'],
                'rating_scores_display_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_scores');
    }
};
