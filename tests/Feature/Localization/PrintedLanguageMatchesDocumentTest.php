<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Models\ContentPage;
use App\Support\Localization\SiteText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FF-249 — BASILAN DİL ile İLAN EDİLEN DİL aynıdır.
 *
 * ── Ölçülen kusur (2026-09-08) ───────────────────────────────────────────
 *
 * Tek istek, tek ekran, üç kusur:
 *
 *     curl -H 'Accept-Language: tr-TR,tr;q=0.9' http://127.0.0.1:8131/
 *
 *     <html lang="en">
 *     <title>Restoran menüsü ve çalışma alanı — Zabuno</title>
 *     …>Create an account…      (İngilizce)
 *     …>Çalışma alanını aç…     (Türkçe)
 *     …>Fiyat…                  (Türkçe)
 *
 * 1. `i18n.shipped_locales` yalnız `['en']` olmasına rağmen Türkçe gövde
 *    veriliyordu. `NegotiateLocale` bu listeye 2026-09-05'te bağlanmıştı ve
 *    doğru çalışıyordu — ama kurumsal kabuk o pazarlığı hiç okumuyor, kendi
 *    `getPreferredLanguage(['en', 'tr'])` çağrısıyla İKİNCİ bir seçim
 *    yapıyordu. Sonuç: aynı istekte iki farklı "seçilmiş dil".
 *
 * 2. Belge kendini `en` ilan ederken gövdesi Türkçeydi. Ekran okuyucu Türkçe
 *    cümleyi İngilizce sesletimle okur; arama motoru sayfayı yanlış dilde
 *    indeksler. Bu tek başına bir erişilebilirlik kusurudur.
 *
 * 3. Türkçe `site` kataloğunun 227 metninden 135'i BOŞ olduğu için gövdenin
 *    kendisi de iki dilliydi. Yarım çeviri çevirisizlikten kötüdür — çünkü
 *    çevirisizlik en azından tutarlıdır.
 *
 * ── Bu testler NEYİ ölçüyor ──────────────────────────────────────────────
 *
 * Var olan `RequestLocaleNegotiationTest` yalnız `<html lang>` ÖZNİTELİĞİNİ
 * ölçüyordu ve o öznitelik zaten doğruydu (`en`). Kusur gövdedeydi. Bu yüzden
 * buradaki ölçüm metnin KENDİSİNE bakar: aynı katalog anahtarının iki dildeki
 * karşılığı farklıysa, sayfada hangisinin durduğu bir dil sezgisi değil, bir
 * dize karşılaştırmasıdır.
 *
 * Testler bir dil ADI taşımaz. Sahip yarın listeye Türkçeyi geri koyarsa da,
 * başka bir dil eklerse de aynı kural ölçülür: sayfada basılan arayüz metni
 * SUNULAN dildendir ve `<html lang>` onunla aynıdır.
 *
 * Requirement ID'leri: I18N-PRINTED-SHIPPED-01, I18N-PRINTED-LANG-MATCH-02,
 * I18N-PRINTED-CONTENT-03, I18N-PRINTED-CHROME-04.
 */
final class PrintedLanguageMatchesDocumentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Kurumsal kabuğun her adresinde aynı kabuk metni basılır; hepsi
     * ölçülür, çünkü kusur denetleyici başına tekrarlanıyordu.
     *
     * @return list<string>
     */
    private const SHELL_PATHS = ['/', '/pricing', '/contact', '/about', '/terms'];

    /**
     * Sunulmayan bir dili İSTEYEN tarayıcı, o dilde HİÇBİR arayüz metni
     * görmez.
     *
     * ── I18N-PRINTED-SHIPPED-01 ──────────────────────────────────────────
     *
     * Ölçüm, "Türkçe var mı" diye bakmaz — bakamaz da, bir dize hangi dilde
     * olduğunu söylemez. Bunun yerine kataloğun kendisine sorulur: aynı
     * anahtarın sunulan dildeki karşılığı ile sunulmayan dildeki karşılığı
     * FARKLIYSA, sayfada yalnız birincisi durabilir.
     */
    public function test_a_browser_asking_for_an_unshipped_language_gets_no_text_in_it(): void
    {
        // This fallback scenario intentionally offers only English.
        config(['i18n.shipped_locales' => ['en']]);

        /** @var list<string> $shipped */
        $shipped = config('i18n.shipped_locales');

        $unshipped = $this->anUnshippedLocaleWithACatalog($shipped);

        foreach (self::SHELL_PATHS as $path) {
            $html = $this->fetch($path, 'tr-TR,tr;q=0.9');

            foreach ($this->distinguishingLabels($shipped[0], $unshipped) as $key => [$shippedLabel, $unshippedLabel]) {
                self::assertStringNotContainsString(
                    $unshippedLabel,
                    $html,
                    "I18N-PRINTED-SHIPPED-01: [{$path}] `{$key}` sunulmayan `{$unshipped}` dilinde basıldı "
                    ."(\"{$unshippedLabel}\"). Sunulan diller: ".implode(', ', $shipped).'. '
                    .'Yarım çevrilmiş bir katalogla arayüz çizmek, aynı ekranda iki dil gösterir.'
                );

                self::assertStringContainsString(
                    $shippedLabel,
                    $html,
                    "I18N-PRINTED-SHIPPED-01: [{$path}] `{$key}` sunulan dilde de basılmamış — "
                    .'sayfa metinsiz kalmış olamaz.'
                );
            }
        }
    }

    /**
     * `<html lang>` GERÇEKTEN BASILAN dille aynıdır.
     *
     * ── I18N-PRINTED-LANG-MATCH-02 ───────────────────────────────────────
     *
     * Kusurun tam kalbi buydu: öznitelik bir kaynaktan (`app()->getLocale()`),
     * gövde başka bir kaynaktan (denetleyicilerin kendi pazarlığından)
     * geliyordu. İkisinin ayrışması mümkün olduğu sürece, bir gün ayrışırlar.
     *
     * Ölçüm iki yönlüdür: ilan edilen dilin metni sayfada VARDIR ve öteki
     * dilin metni sayfada YOKTUR. Yalnız birincisi ölçülseydi, iki dili birden
     * basan bir sayfa da geçerdi — kusurun kendisi tam olarak buydu.
     */
    public function test_the_declared_document_language_is_the_language_actually_printed(): void
    {
        foreach (['tr-TR,tr;q=0.9', 'tr', 'de', 'ja,ko;q=0.8', 'en-GB,en;q=0.9'] as $header) {
            foreach (self::SHELL_PATHS as $path) {
                $html = $this->fetch($path, $header);

                $declared = $this->documentLanguage($html);

                self::assertContains(
                    $declared,
                    (array) config('i18n.shipped_locales'),
                    "I18N-PRINTED-LANG-MATCH-02: [{$header} {$path}] belge kendini `{$declared}` ilan etti "
                    .'ama o dil sunulmuyor.'
                );

                foreach ($this->distinguishingLabels($declared, $this->anUnshippedLocaleWithACatalog([$declared])) as $key => [$declaredLabel, $otherLabel]) {
                    self::assertStringContainsString(
                        $declaredLabel,
                        $html,
                        "I18N-PRINTED-LANG-MATCH-02: [{$header} {$path}] belge `{$declared}` diyor ama "
                        ."`{$key}` o dilde basılmamış."
                    );

                    self::assertStringNotContainsString(
                        $otherLabel,
                        $html,
                        "I18N-PRINTED-LANG-MATCH-02: [{$header} {$path}] belge `{$declared}` diyor ama sayfada "
                        ."başka bir dilin metni duruyor (\"{$otherLabel}\"). Ekran okuyucu o cümleyi yanlış "
                        .'sesletimle okur.'
                    );
                }
            }
        }
    }

    /**
     * GERÇEKTEN YAZILMIŞ bir Türkçe belge hâlâ `lang="tr"` alır.
     *
     * ── I18N-PRINTED-CONTENT-03 ──────────────────────────────────────────
     *
     * İÇERİK dili ile ARAYÜZ dili ayrı kavramlardır ve bu düzeltme ikincisini
     * kısıtlarken birincisine dokunmaz. `resources/help/tr/first-15-minutes`
     * baştan sona Türkçe YAZILMIŞTIR — bir katalog parçası değil, bir belge.
     * Onu saklamak, var olan bir metni sırf kataloğun eksik olduğu için
     * göstermemek olurdu; ve orada `lang="tr"` doğrudur.
     *
     * Bu test aynı zamanda düzeltmenin AŞIRIYA KAÇMADIĞINI ölçer: "her şeyi
     * İngilizce ver" de birinci ve ikinci testi geçerdi.
     */
    public function test_a_page_actually_written_in_turkish_still_declares_turkish(): void
    {
        $html = $this->fetch('/help', 'tr-TR,tr;q=0.9');

        self::assertSame(
            'tr',
            $this->documentLanguage($html),
            'I18N-PRINTED-CONTENT-03: Türkçe YAZILMIŞ yardım makalesi Türkçe bir belge olarak gelmiyor.'
        );

        // Makalenin kendi cümlesi — katalogdan değil, dosyadan.
        self::assertStringContainsString(
            'İlk 15 dakikanız',
            $html,
            'I18N-PRINTED-CONTENT-03: makale Türkçe ilan edildi ama Türkçe metni yok.'
        );
    }

    /**
     * O belgenin İÇİNDEKİ arayüz kabuğu KENDİ dilini ilan eder.
     *
     * ── I18N-PRINTED-CHROME-04 ───────────────────────────────────────────
     *
     * Yalnız İngilizce sunulan test kurulumunda, adresi Türkçe olan kütük
     * sayfasının kabuğu İngilizcedir. Hazırlanıyor sayfası 404 döner; dil
     * farkı yine açıkça ilan edilmelidir. Güncel /help ise seçilen dilde
     * hem makale hem kabuk sunar ve bu yabancı belge senaryosu değildir.
     */
    public function test_the_chrome_inside_a_foreign_language_document_declares_its_own_language(): void
    {
        // This fallback scenario intentionally offers only English.
        config(['i18n.shipped_locales' => ['en']]);

        // Help now negotiates article and chrome together; a locale-addressed
        // registry document supplies the intentional foreign-language fixture.
        ContentPage::query()->create([
            'page_key' => 'urun.qr-menu',
            'locale' => 'tr',
            'canonical_path' => '/tr/urun/qr-menu/',
            'content_type' => 'urun',
            'template_key' => 'urun',
            'title' => 'QR Menü',
            'priority' => 'P0',
            'publication_status' => 'planned',
            'was_ever_published' => false,
        ]);
        $html = (string) $this->withHeader('Accept-Language', 'en')
            ->get('/tr/urun/qr-menu/')->assertNotFound()->getContent();
        self::assertSame('tr', $this->documentLanguage($html));

        $ui = SiteText::pick('tr');

        self::assertNotSame('tr', $ui, 'ölçüm kurulumu bozuk: kabuk da Türkçe olsaydı ilan edilecek bir fark olmazdı');

        preg_match('#<header\b[^>]*>#', $html, $header);

        self::assertNotSame([], $header, 'Sayfada üst çubuk yok — ölçüm dayanaksız.');

        self::assertStringContainsString(
            'lang="'.$ui.'"',
            $header[0],
            'I18N-PRINTED-CHROME-04: Türkçe bir belgenin içindeki İngilizce üst çubuk kendi dilini ilan etmiyor.'
        );
    }

    // --- ölçüm araçları ---------------------------------------------------

    private function fetch(string $path, string $acceptLanguage): string
    {
        return (string) $this->withHeaders(['Accept-Language' => $acceptLanguage])
            ->get($path)
            ->assertOk()
            ->getContent();
    }

    private function documentLanguage(string $html): string
    {
        preg_match('/<html lang="([a-zA-Z-]+)"/', $html, $matches);

        self::assertNotSame([], $matches, 'Belgede `lang` yazmıyor.');

        return $matches[1];
    }

    /**
     * Kataloğu DERLENMİŞ ama sunulmayan bir dil.
     *
     * Test bir dil adı sabitlemez: sunulan liste yarın değişirse ölçüm
     * kendiliğinde başka bir dile kayar ve kural aynı kalır.
     *
     * @param  list<string>  $shipped
     */
    private function anUnshippedLocaleWithACatalog(array $shipped): string
    {
        /** @var list<string> $compiled */
        $compiled = (array) config('app.supported_locales', []);

        foreach ($compiled as $locale) {
            if (! in_array($locale, $shipped, true) && $this->hasDistinctLabels($shipped[0] ?? 'en', $locale)) {
                return $locale;
            }
        }

        self::fail('Karşılaştırılacak, sunulmayan ama kataloğu dolu bir dil yok — ölçüm dayanaksız.');
    }

    private function hasDistinctLabels(string $a, string $b): bool
    {
        return $this->distinguishingLabels($a, $b) !== [];
    }

    /**
     * İki dilde FARKLI karşılığı olan kabuk etiketleri.
     *
     * Aynı karşılığı olanlar (marka adı gibi) ölçüme giremez: onlar hangi
     * dilde basıldığını söylemez.
     *
     * @return array<string, array{string, string}>
     */
    private function distinguishingLabels(string $left, string $right): array
    {
        /** @var SiteText $siteText */
        $siteText = app(SiteText::class);

        $leftAll = $siteText->all($left);
        $rightAll = $siteText->all($right);

        $out = [];

        // Kabuğun HER sayfada bulunan, gövdeye gerçekten basılan etiketleri.
        foreach (['navPricing', 'navContact', 'navHelp', 'footerTagline'] as $key) {
            $leftLabel = $leftAll[$key] ?? '';
            $rightLabel = $rightAll[$key] ?? '';

            if ($leftLabel === '' || $rightLabel === '' || $leftLabel === $rightLabel) {
                continue;
            }

            $out[$key] = [$leftLabel, $rightLabel];
        }

        return $out;
    }
}
