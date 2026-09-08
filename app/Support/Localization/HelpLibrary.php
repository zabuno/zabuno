<?php

declare(strict_types=1);

namespace App\Support\Localization;

/**
 * Yardım makaleleri — `docs/89` (P1-01 üçüncü ölçüt), `docs/107` Faz 2.8.
 *
 * BELGE, ARAYÜZ ETİKETİ DEĞİL.
 *
 * Bir makale 40'tan fazla cümle taşır. Cümle başına katalog anahtarı
 * makaleler için yanlış şekildir: çevirmen bağlamı göremez, bir paragrafı
 * ikiye bölmek anahtar listesini bozar ve gözden geçiren metni bir bütün
 * olarak okuyamaz. Makaleler DİLE GÖRE DOSYA olarak yaşar — her
 * dokümantasyon sitesinin yaptığı budur.
 *
 * Dosyalar `resources/views` DIŞINDA durur ve ayrı bir görünüm alanı olarak
 * kaydedilir. Sebep şekilsel: çevrilemez-dize sayacı arayüz şablonlarını
 * ölçer ve bir makaleyi orada saymak, ölçümü anlamsızlaştırırdı.
 *
 * ═══ TEK MAKALEDEN MERKEZE (FF-230) ═══
 *
 * `docs/106` yardım ağacında 23 satır sayıyordu; depoda GERÇEKTE tek makale
 * vardı. Liste artık burada ve SIRALI: giriş sayfası bu sıradan çizilir,
 * rotalar bu sıradan üretilir, kapı bu sırayı gezer. Bir makale eklemek tek
 * bir dizi girdisi ve bir dosyadır; kayıt ile dosya arasında ayrışma
 * olamaz, çünkü kapı ikisini karşılaştırır.
 *
 * ═══ ADRESLER KALICIDIR ═══
 *
 * `/help` giriş makalesidir ve öyle KALIR: panelin ilk-kez ipuçları ona ve
 * onun çıpalarına bağlanıyor (`resources/js/.../firstRunHints.ts`). Yeni
 * makaleler `/help/<slug>` altında durur; her biri desensiz, LİTERAL bir
 * rotadır, çünkü statik site dışa aktarımı desen taşıyan bir rotayı bir
 * sayfa saymaz — desenle yazılsalardı 320 piksel ölçümüne hiç girmezlerdi.
 */
final class HelpLibrary
{
    /** Yardımın giriş makalesi: `/help` adresinin kendisi. */
    public const ENTRY = 'first-15-minutes';

    /** Kaynak dil (`docs/118` E4). Her makale bu dilde VARDIR. */
    public const SOURCE = 'en';

    /** Yardımın bugün konuştuğu diller. */
    public const SUPPORTED = ['en', 'tr'];

    /**
     * ÇEVRİLMİŞ makaleler — adıyla sayılı.
     *
     * Çeviri kilidi sahibin açık komutuna bağlıdır (`docs/107` Ufuk 30);
     * bu yüzden liste bir hedef değil, bir ÖLÇÜMDÜR: bugün gerçekten
     * çevrilmiş olan makale budur. Listedeki bir makalenin dosyası
     * eksikse kapı kırılır; listede olmayan makale kaynak dilde açılır ve
     * bunu okuyucuya söyler.
     */
    public const TRANSLATED = [self::ENTRY];

    /**
     * Yardım merkezinin makaleleri — giriş sayfasındaki sıra.
     *
     * Sıra keyfî değil: sahip yardıma sırayla gelmez, ama ilk gün
     * sorularının önce durması, üçüncü ay sorularının sonra gelmesi bir
     * telefonda kaydırma mesafesi demektir.
     */
    public const ARTICLES = [
        self::ENTRY,
        'add-your-restaurant',
        'nothing-changed-for-my-guests',
        'table-cards-and-areas',
        'a-photo-on-a-dish',
        'menus-that-change-during-the-day',
        'who-can-do-what',
        'guest-ratings',
        'plan-and-invoices',
    ];

    private const FALLBACK = self::SOURCE;

    /** Bir makalenin genel adresi. */
    public static function pathOf(string $slug): string
    {
        return $slug === self::ENTRY ? '/help' : '/help/'.$slug;
    }

    /** Adresten makale — bilinmeyen bir slug `null` döner (çağıran 404 verir). */
    public static function slugForPath(string $path): ?string
    {
        $path = '/'.trim($path, '/');

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

    /** Bu makale okuyucunun dilinde var mı? */
    public static function isTranslatedInto(string $locale, string $slug): bool
    {
        return $locale !== self::SOURCE
            && in_array($slug, self::TRANSLATED, true)
            && is_file(self::pathFor($locale, $slug));
    }

    /**
     * Okuyucunun diline en yakın makale.
     *
     * Dosya yoksa kaynak dile düşer. Bu düşüş SESSİZ DEĞİLDİR: makalenin
     * kendisi kaynak dilde olduğunu yazar, çünkü sessiz bir yedek okuyucuya
     * yardımın bozuk olduğunu düşündürür.
     */
    public static function viewFor(?string $preferred, string $slug = self::ENTRY): string
    {
        $locale = self::localeFor($preferred);

        if (! self::isTranslatedInto($locale, $slug)) {
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

    /**
     * Sayfanın GERÇEK dili — `<html lang>` bunu söyler.
     *
     * Türkçe isteyen okuyucuya İngilizce makale açılıyorsa sayfa `lang="en"`
     * bildirmek ZORUNDADIR; aksi hâlde ekran okuyucu İngilizce metni Türkçe
     * telaffuz eder.
     */
    public static function documentLocaleFor(?string $preferred, string $slug = self::ENTRY): string
    {
        $locale = self::localeFor($preferred);

        return self::isTranslatedInto($locale, $slug) ? $locale : self::FALLBACK;
    }
}
