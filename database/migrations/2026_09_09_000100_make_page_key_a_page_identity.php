<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `page_key` bir SAYFANIN kimliği, bir SATIRIN değil — `docs/120` §1, §5(7).
 *
 * Kütük kurulduğunda `page_key` GENEL olarak benzersizdi ve o gün bu doğru
 * görünüyordu: kaynak dil Türkçeydi, her sayfanın tek bir kaydı vardı ve
 * `SiteMapParser` anahtardan dil dizinini düşürürken "aynı sayfanın Türkçesi
 * ve İngilizcesi TEK bir kayıttır" diye yazılmıştı.
 *
 * Sonucu ölçüldü: `site:import-map` 386 satır üretiyor ve hepsi `tr`. Kaynak
 * dilin İngilizce olduğu bir sitede (`docs/118` E4) kütükte tek bir İngilizce
 * sayfa yoktu ve OLAMAZDI — çünkü aynı anahtarı taşıyan ikinci bir satır
 * kısıt tarafından reddediliyordu. hreflang, `x-default` ve dil
 * değiştiricinin "aynı sayfada kal" sözü bu tek kısıt yüzünden imkânsızdı.
 *
 * KISIT GEVŞEMİYOR, YER DEĞİŞTİRİYOR. Benzersizlik `page_key + locale`
 * üzerine taşınıyor: bir dilde aynı sayfanın iki kaydı olamaz. Olsaydı dil
 * değiştirici hangisine gideceğini bilemez, hreflang aynı dili iki adresle
 * ilan ederdi. `locale + canonical_path` benzersizliği yerinde duruyor.
 *
 * GERİYE UYUMLU: bugünkü 386 satırın anahtarları zaten birbirinden farklı,
 * dolayısıyla yeni kısıt onların hiçbirini reddetmez. Tek satır silinmiyor,
 * tek anahtar yeniden adlandırılmıyor (`docs/121` Ö12: anahtar bir kimliktir).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_pages', function (Blueprint $table) {
            $table->dropUnique('content_pages_page_key_unique');

            /*
                İndeks adı ELLE veriliyor. Laravel'in ürettiği ad
                (`content_pages_page_key_locale_unique`) bu tabloda 40 karakter
                ve sorun değil, ama ad üretimi tablo adına bağlıdır ve
                PostgreSQL tanımlayıcıları 63 karakterde SESSİZCE kesilir —
                kesilen iki ad çakıştığında hata göç sırasında değil, aylar
                sonra bir kısıt ihlalinde çıkar. Kısa ve açık ad bu riski
                tamamen ortadan kaldırır.
            */
            $table->unique(['page_key', 'locale'], 'content_pages_key_locale_unique');

            /*
                Anahtar TEK BAŞINA da aranır: dil değiştirici "bu sayfanın
                öteki dilleri" sorusunu `page_key` ile sorar. Eski benzersiz
                indeks bu aramayı da hızlandırıyordu; onu düşürüp yerine bir
                şey koymamak, her dil değiştirici çiziminde tam tarama
                bırakmak olurdu.
            */
            $table->index('page_key', 'content_pages_page_key_index');
        });
    }

    /**
     * Geri dönüş ANCAK kütük hâlâ tek dilliyken mümkündür — ve bu bir kusur
     * değil, bir dürüstlüktür.
     *
     * `page_key`'i yeniden genel benzersiz yapmak, iki dili olan her sayfanın
     * dillerinden birini SİLMEYİ gerektirir. Bir göç, hangi dilin gideceğine
     * kendi başına karar veremez; karar verirse bir gün birinin yazdığı içerik
     * sessizce yok olur.
     *
     * Bu yüzden kontrol İLK SIRADA ve şemaya dokunmadan önce yapılır: yarım
     * uygulanmış bir geri dönüş, hiç uygulanmamış olandan kötüdür — tablo ne
     * eski kısıtını taşır ne yenisini.
     */
    public function down(): void
    {
        $multilingual = DB::table('content_pages')
            ->select('page_key')
            ->groupBy('page_key')
            ->havingRaw('count(*) > 1')
            ->limit(1)
            ->get();

        if ($multilingual->isNotEmpty()) {
            throw new RuntimeException(
                'content_pages çok dilli satırlar taşıyor ('.$multilingual->first()->page_key.
                ' gibi). Geri dönüş, her sayfanın dillerinden birini silmeyi gerektirir; '.
                'hangi dilin gideceğine bir göç karar veremez. Önce fazla dilleri elle kaldırın.',
            );
        }

        Schema::table('content_pages', function (Blueprint $table) {
            $table->dropIndex('content_pages_page_key_index');
            $table->dropUnique('content_pages_key_locale_unique');
            $table->unique('page_key', 'content_pages_page_key_unique');
        });
    }
};
