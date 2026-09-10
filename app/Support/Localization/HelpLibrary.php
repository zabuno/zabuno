<?php

declare(strict_types=1);

namespace App\Support\Localization;

/**
 * Yardım makaleleri — `docs/89` (P1-01 üçüncü ölçüt).
 *
 * BELGE, ARAYÜZ ETİKETİ DEĞİL.
 *
 * Bir makale 40'tan fazla cümle taşıyor. Cümle başına katalog anahtarı
 * makaleler için yanlış şekildir: çevirmen bağlamı göremez, bir paragrafı
 * ikiye bölmek anahtar listesini bozar ve gözden geçiren metni bir bütün
 * olarak okuyamaz. Makaleler DİLE GÖRE DOSYA olarak yaşar — her dokümantasyon
 * sitesinin yaptığı budur.
 *
 * Dosyalar `resources/views` DIŞINDA durur ve ayrı bir görünüm alanı olarak
 * kaydedilir. Sebep şekilsel: çevrilemez-dize sayacı arayüz şablonlarını
 * ölçer ve bir makaleyi orada saymak, ölçümü anlamsızlaştırırdı. Karşılığında
 * bir kapı geliyor — desteklenen her dilin dosyası VAR OLMAK ZORUNDA
 * (`HelpContentTest`), yani eksik bir çeviri kullanıcıya değil CI'a görünür.
 *
 * ═══ TEK MAKALEDEN KÜTÜPHANEYE (HELP-PHOTO-01) ═══
 *
 * Kütüphane artık BİRDEN ÇOK makale taşıyor ve liste burada, SIRALI duruyor:
 * rotalar bu sıradan üretilir, kapı bu sırayı gezer. Bir makale eklemek tek
 * bir dizi girdisi ve dil başına bir dosyadır; kayıt ile dosya arasında
 * ayrışma olamaz, çünkü kapı ikisini karşılaştırır.
 *
 * ═══ HER MAKALE HER DİLDE TAMDIR ═══
 *
 * Burada "şu makale henüz çevrilmedi" diye bir liste YOK ve bilerek yok.
 * Böyle bir liste, yarım çevrilmiş bir makaleyi meşrulaştıran bir borç
 * defteri olurdu: okuyucu Türkçe isterken İngilizce metin görür, sayfa bunu
 * bir uyarı cümlesiyle örter ve kimse borcu kapatmaz. Kapı bunun yerine
 * DİL × MAKALE kümesinin tamamını ölçer — eksik bir çeviri makaleyi
 * yayınlanamaz yapar, okunabilir ama yanlış dilde yapmaz.
 *
 * ═══ ADRESLER ALLOWLIST'TİR ═══
 *
 * `/help` giriş makalesidir ve öyle KALIR; paneldeki ilk-kez ipuçları ona ve
 * onun çıpalarına bağlanıyor. Diğer makaleler `/help/<slug>` altında durur ve
 * slug adresten bir dosya yoluna ÇEVRİLMEZ, listede ARANIR. Aksi hâlde bir
 * yardım adresi, deponun geri kalanını yoklamanın en ucuz yolu olurdu.
 */
final class HelpLibrary
{
    /** Yardımın giriş makalesi: `/help` adresinin kendisi. */
    public const ENTRY = 'first-15-minutes';

    /** Yardımın bugün konuştuğu diller. */
    public const SUPPORTED = ['en', 'tr'];

    /**
     * Kütüphanenin makaleleri — giriş makalesindeki bağlantı sırası.
     *
     * Sıra keyfî değil: sahip yardıma sırayla gelmez, ama ilk gün
     * sorularının önce durması bir telefonda kaydırma mesafesi demektir.
     *
     * "Misafirimde hiçbir şey değişmedi" fotoğraftan ÖNCE durur ve bu
     * sıralama bir tercih değil, bir sıklık ölçüsüdür: kaydet-yayınla ayrımı
     * ilk oturumun sonunda herkesin çarptığı duvardır, ürüne fotoğraf koymak
     * ise ikinci günün işidir. Panik hâlindeki okuru kaydırmaya zorlamak,
     * yardımın işe yaramadığı anlamına gelir.
     *
     * Masa kartı makalesi EN SONDA durur ve bu da bir sıklık ölçüsüdür:
     * kart basmak bir restoranın ömründe bir ya da iki kez yaptığı iştir,
     * fiyat düzeltmek ise her hafta. Ama makale ilk günün de işidir — giriş
     * makalesinin karekod bölümü doğrudan ona bağlanır, çünkü orada anlatılan
     * üç satır kırk masalı bir salona yetmez.
     */
    public const ARTICLES = [
        self::ENTRY,
        'nothing-changed-for-my-guests',
        'a-photo-on-a-dish',
        'table-cards-and-areas',
    ];

    private const FALLBACK = 'en';

    /** Bir makalenin genel adresi. */
    public static function pathOf(string $slug): string
    {
        return $slug === self::ENTRY ? '/help' : '/help/'.$slug;
    }

    /**
     * Adresten makale — KAYITLI OLMAYAN her şey `null` (çağıran 404 verir).
     *
     * Karşılaştırma listenin ürettiği adrese karşı yapılır, slug'a karşı
     * değil: böylece `/help/first-15-minutes` de bir sayfa DEĞİLDİR, çünkü
     * giriş makalesinin tek adresi vardır ve iki adresli bir makale iki ayrı
     * sayfa olarak indekslenirdi.
     */
    public static function slugForPath(string $path): ?string
    {
        $path = rtrim($path, '/');

        if ($path === '') {
            $path = '/';
        }

        foreach (self::ARTICLES as $slug) {
            if (self::pathOf($slug) === $path) {
                return $slug;
            }
        }

        return null;
    }

    public static function pathFor(string $locale, string $slug = self::ENTRY): string
    {
        return resource_path('help/'.$locale.'/'.$slug.'.blade.php');
    }

    /**
     * Okuyucunun diline en yakın makale.
     *
     * Dosya yoksa yedeğe düşer; ama bu durum bir kapıyla ZATEN engellenmiş
     * olmalı — burada düşmek, üretimde beyaz ekran göstermemek içindir.
     */
    public static function viewFor(?string $preferred, string $slug = self::ENTRY): string
    {
        $locale = self::localeFor($preferred);

        if (! is_file(self::pathFor($locale, $slug))) {
            $locale = self::FALLBACK;
        }

        return 'help::'.$locale.'.'.$slug;
    }

    public static function localeFor(?string $preferred): string
    {
        return in_array((string) $preferred, self::SUPPORTED, true)
            ? (string) $preferred
            : self::FALLBACK;
    }
}
