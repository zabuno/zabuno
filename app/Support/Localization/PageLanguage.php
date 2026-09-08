<?php

declare(strict_types=1);

namespace App\Support\Localization;

/**
 * Bir kurumsal sayfanın İKİ dili — ve ikisinin AYRIŞAMAMASI.
 *
 * ── Ölçülen kusur (2026-09-08) ───────────────────────────────────────────
 *
 * `Accept-Language: tr-TR` ile ana sayfa istendiğinde gelen belge şuydu:
 *
 *     <html lang="en">
 *     <title>Restoran menüsü ve çalışma alanı — Zabuno</title>
 *     …>Create an account…      (İngilizce)
 *     …>Çalışma alanını aç…     (Türkçe)
 *     …>Fiyat…                  (Türkçe)
 *
 * Üç kusur tek ekranda: (1) `i18n.shipped_locales` yalnız `en` olmasına
 * rağmen Türkçe metin basılıyordu; (2) belge kendini `en` ilan ediyordu ama
 * gövdesi Türkçeydi — ekran okuyucu Türkçe cümleyi İngilizce sesletimle
 * okur, arama motoru sayfayı yanlış dilde indeksler; (3) iki dil yan yana
 * duruyordu, çünkü Türkçe `site` kataloğunun 227 metninden 135'i boş.
 *
 * ── Neden iki ayrı kavram ────────────────────────────────────────────────
 *
 * `ui` — ÜRÜNÜN kendi etiketleri (gezinti, altbilgi, düğme adları). Bunlar
 * katalogdan gelir ve bir katalog YARIM olabilir. Yarım çeviri
 * çevirisizlikten kötüdür — çünkü çevirisizlik en azından tutarlıdır — bu
 * yüzden `ui` HER ZAMAN `i18n.shipped_locales` içindedir.
 *
 * `document` — sayfanın ASIL metninin dili. `/tr/…` altındaki bir kurumsal
 * sayfa ya da `resources/help/tr/…` altındaki bir yardım makalesi gerçekten
 * Türkçe YAZILMIŞTIR; orada `lang="tr"` doğrudur ve bir katalogla ilgisi
 * yoktur. Bu ayrım `shipped_locales`i genişletmez: yazılmış bir belge
 * sunmak ile yarım bir katalogla arayüz çevirmek aynı şey değildir.
 *
 * ── Ayrışmayı imkânsız kılan şey ─────────────────────────────────────────
 *
 * Kusur, iki değerin İKİ AYRI YERDEN gelmesiydi: `<html lang>` uygulamanın
 * pazarlıkla seçilmiş dilinden, gövde ise denetleyicilere elle yazılmış
 * `getPreferredLanguage(['en', 'tr'])` çağrılarından. İki kaynak varken
 * tutarlılık dikkate kalır ve dikkat bir kapı değildir.
 *
 * Burada tek kaynak var: metnin çizildiği dil (`ui`) ile belgenin ilan ettiği
 * dil (`document`) AYNI nesnede, aynı anda doğar. Asıl metni olmayan bir
 * sayfada `document` zaten `ui`nin kendisidir — yani ayrışacak bir şey yoktur.
 * Asıl metni olan sayfada ise ikisi bilerek farklıdır ve fark, kabuk
 * bölgelerine kendi `lang`/`dir`i yazılarak ilan edilir; HTML'in `lang`
 * özniteliği tam olarak bunun içindir.
 */
final class PageLanguage
{
    private function __construct(
        /** Ürünün kendi etiketlerinin dili — her zaman sunulan bir dil. */
        public readonly string $ui,
        /** Belgenin asıl metninin dili; `<html lang>` budur. */
        public readonly string $document,
    ) {}

    /**
     * @param  string|null  $contentLocale  Sayfanın YAZILMIŞ metninin dili
     *                                      (kurumsal kaydın `locale`i, yardım
     *                                      makalesinin dosya dili). Böyle bir
     *                                      metin yoksa `null`.
     */
    public static function for(?string $contentLocale = null): self
    {
        /*
            Arayüz dili PAZARLIKTAN gelir ve pazarlık `NegotiateLocale`de,
            `i18n.shipped_locales` üzerinden bir kez yapılır. Burada ikinci
            bir pazarlık yapmak — denetleyicilerin yaptığı buydu — ürüne iki
            farklı "seçilmiş dil" verirdi ve ekranda ikisi de görünürdü.

            Süzgeç yine de duruyor: konsol komutları ve testler locale'i elle
            kurabilir, ve sunulmayan bir dilde arayüz çizmek yarım çeviriyi
            geri getirirdi.
        */
        $ui = SiteText::pick(app()->getLocale());

        $document = self::baseLanguage((string) $contentLocale);

        return new self($ui, $document === '' ? $ui : $document);
    }

    /**
     * Kabuğun kendi dili — belgeninkinden FARKLIYSA.
     *
     * `null` dönmesi "yazacak bir şey yok" demektir: sayfanın tamamı tek
     * dilde. Türkçe bir yardım makalesinin çevresindeki İngilizce üst çubuk
     * ise kendi dilini ilan eder, yoksa ekran okuyucu onu da Türkçe sesletimle
     * okurdu.
     */
    public function chromeLanguage(): ?string
    {
        return $this->ui === $this->document ? null : $this->ui;
    }

    /**
     * Kabuk bölgesine yazılacak `lang`/`dir` ÖZNİTELİKLERİ — baştaki boşluk
     * dahil, ya da diller aynıysa boş dize.
     *
     * Neden şablonda `@if` değil de burada: bir Blade parçası olarak
     * denendi ve `<header class="site-header"lang="en">` üretti — öznitelikler
     * bitişik, yani GEÇERSİZ HTML. Bir öznitelik dizisinin baştaki boşluğu
     * onun parçasıdır; şablona bırakıldığında görünmez bir karaktere
     * güvenmek olurdu.
     */
    public function chromeAttributes(): string
    {
        $language = $this->chromeLanguage();

        if ($language === null) {
            return '';
        }

        return ' lang="'.e($language).'" dir="'.e($this->chromeDirection()).'"';
    }

    /** `<html lang>` değeri. */
    public function documentTag(): string
    {
        return DocumentLocale::tag($this->document);
    }

    /** `<html dir>` — yön dilin özelliğidir, şablonun kararı değil. */
    public function documentDirection(): string
    {
        return DocumentLocale::direction($this->document);
    }

    /**
     * Kabuk bölgelerinin yönü. Sağdan sola yazılan bir belgenin içindeki
     * soldan sağa bir üst çubuk, yönünü de söylemek zorundadır; yalnız `lang`
     * yazmak metni doğru sesletir ama düzeni ters bırakırdı.
     */
    public function chromeDirection(): string
    {
        return DocumentLocale::direction($this->ui);
    }

    private static function baseLanguage(string $tag): string
    {
        return strtolower(explode('-', str_replace('_', '-', trim($tag)))[0]);
    }
}
