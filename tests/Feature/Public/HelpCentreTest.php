<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Support\Localization\HelpLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FF-230 RED — yardım MERKEZİ (`docs/107` Faz 2.8, `docs/89`, `docs/106`).
 *
 * ═══ ÖLÇÜLEN HÂL ═══
 *
 * `docs/106` yardım ağacında 23 satır sayıyor; depoda GERÇEKTE tek bir
 * makale vardı ("İlk 15 dakika"). Panelin destek ekranı "Open help
 * articles" diyordu — çoğul — ve tek bir makaleye gidiyordu.
 *
 * ═══ BU KAPI NEYİ DONDURUYOR ═══
 *
 * Yardım makalesinin kitlesi ÜRÜNÜN KENDİ KULLANICISIDIR: restoran sahibi.
 * O kişi teknoloji bilmez, telefondan okur ve buraya bir şey ters gittiği
 * için gelmiştir. Bu yüzden üç şey testle bağlanıyor:
 *
 *   1. Makale TEK BİR SORUYA cevap verir ve başlığı o sorudur.
 *   2. Makale NE YAPILAMADIĞINI da söyler. En sık destek çağrısı, olmayan
 *      bir şeyi arayan kullanıcıdan gelir; onu bir cümle keser.
 *   3. Makalede kalın yazılan her ad EKRANDA GERÇEKTEN VARDIR. Ad, arayüz
 *      kataloğundan (`lang/po/*.en.po`) ölçülür. Var olmayan bir düğmeyi
 *      tarif eden yardım kullanıcıyı İKİNCİ kez tıkar: önce özelliği
 *      bulamaz, sonra yardımın da yanıldığını görür ve bir daha açmaz.
 *
 * Requirement ID'leri: HELP-CENTRE-INDEX-01, HELP-CENTRE-NO-AUTH-02,
 * HELP-CENTRE-IDENTITY-03, HELP-CENTRE-ONE-QUESTION-04,
 * HELP-CENTRE-SAYS-WHAT-IT-CANNOT-05, HELP-CENTRE-REAL-LABELS-06,
 * HELP-CENTRE-NO-BREAKPOINT-07, HELP-CENTRE-SOURCE-LANGUAGE-08.
 */
final class HelpCentreTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<array{0:string}> */
    public static function articleSlugs(): array
    {
        return array_map(static fn (string $s): array => [$s], HelpLibrary::ARTICLES);
    }

    /** @return list<array{0:string}> */
    public static function articlePaths(): array
    {
        return array_map(
            static fn (string $s): array => [HelpLibrary::pathOf($s)],
            HelpLibrary::ARTICLES
        );
    }

    // --- HELP-CENTRE-INDEX-01 ---------------------------------------------

    /**
     * Bir makale, GİRİŞ SAYFASINDAN bulunabilir olmalı.
     *
     * Adresini bilenin okuyabildiği bir makale yayınlanmış değildir: onu
     * yalnız yazan bilir.
     */
    public function test_the_entry_page_lists_every_article(): void
    {
        $html = $this->get('/help')->getContent();

        foreach (HelpLibrary::ARTICLES as $slug) {
            if ($slug === HelpLibrary::ENTRY) {
                continue;
            }

            self::assertStringContainsString(
                'href="'.HelpLibrary::pathOf($slug).'"',
                $html,
                "HELP-CENTRE-INDEX-01: [{$slug}] yardım girişinden bağlantılı değil."
            );
        }
    }

    // --- HELP-CENTRE-NO-AUTH-02 -------------------------------------------

    /**
     * Hiçbir makale oturum istemez.
     *
     * Tıkanan biri oturum açamıyor olabilir; yardımın kapı tutması, en çok
     * ihtiyaç duyulduğu anda kapıyı kapatırdı.
     */
    #[DataProvider('articlePaths')]
    public function test_no_article_asks_for_an_account(string $path): void
    {
        $this->get($path)->assertOk();
    }

    // --- HELP-CENTRE-IDENTITY-03 ------------------------------------------

    /**
     * Her makalenin kendi adı, kendi açıklaması ve TEK bir `h1`'i var.
     *
     * Yardım bağlantısı paylaşılır: destek yanıtı bir adres gönderir ve o
     * adres hangi sayfa olduğunu söylemek zorundadır.
     */
    #[DataProvider('articlePaths')]
    public function test_every_article_has_a_name_a_description_and_one_heading(string $path): void
    {
        $html = $this->get($path)->getContent();

        preg_match('#<title>(.*?)</title>#s', $html, $title);
        self::assertNotEmpty(trim($title[1] ?? ''), "HELP-CENTRE-IDENTITY-03: [{$path}] başlıksız.");
        self::assertNotSame('— Zabuno', trim($title[1]), "HELP-CENTRE-IDENTITY-03: [{$path}] yalnız marka adı.");

        preg_match('#<meta name="description" content="([^"]*)">#', $html, $description);
        self::assertNotEmpty(
            trim($description[1] ?? ''),
            "HELP-CENTRE-IDENTITY-03: [{$path}] açıklamasız — paylaşımda ve arama sonucunda boş görünür."
        );

        self::assertSame(
            1,
            preg_match_all('#<h1\b#', $html),
            "HELP-CENTRE-IDENTITY-03: [{$path}] birden çok (ya da hiç) `h1` taşıyor."
        );
    }

    // --- HELP-CENTRE-ONE-QUESTION-04 --------------------------------------

    /**
     * Yeni makalenin başlığı BİR SORUDUR.
     *
     * Sahip yardıma bir soruyla gelir ("kodu nasıl basarım?"). Başlığı bir
     * konu adı olan makale ("QR yönetimi") onu, sorusunun cevabının orada
     * olup olmadığını okuyarak aramaya zorlar.
     *
     * Giriş makalesi ("Your first 15 minutes") bir tur, bir soru değil —
     * ve bu kapı da onu soru saymaz.
     */
    #[DataProvider('articleSlugs')]
    public function test_a_new_article_is_titled_with_the_question_it_answers(string $slug): void
    {
        if ($slug === HelpLibrary::ENTRY) {
            self::assertTrue(true);

            return;
        }

        preg_match('#<h1[^>]*>(.*?)</h1>#s', $this->article($slug), $heading);

        self::assertStringEndsWith(
            '?',
            trim(strip_tags($heading[1] ?? '')),
            "HELP-CENTRE-ONE-QUESTION-04: [{$slug}] başlığı bir soru değil."
        );
    }

    // --- HELP-CENTRE-SAYS-WHAT-IT-CANNOT-05 -------------------------------

    /**
     * Bir soruya cevap veren makale, CEVAPLAYAMADIĞINI da söyler.
     *
     * En sık destek çağrısı olmayan bir şeyi arayan kullanıcıdan gelir:
     * ekranı baştan sona okur, bulamaz, yanlış yaptığını sanır ve yazar.
     * Bir cümle o çağrıyı kesiyor.
     */
    #[DataProvider('articleSlugs')]
    public function test_an_article_that_asks_a_question_also_says_what_cannot_be_done(string $slug): void
    {
        if ($slug === HelpLibrary::ENTRY) {
            self::assertTrue(true);

            return;
        }

        self::assertStringContainsString(
            'id="what-you-cannot-do"',
            $this->article($slug),
            "HELP-CENTRE-SAYS-WHAT-IT-CANNOT-05: [{$slug}] ne yapılamadığını söylemiyor."
        );
    }

    // --- HELP-CENTRE-REAL-LABELS-06 ---------------------------------------

    /**
     * Makalede KALIN yazılan her ad ekranda gerçekten var.
     *
     * Kaynak dildeki makalelerde `<strong>` yalnız EKRAN ADI için kullanılır
     * — vurgu için değil. Böylece "ekranda ne yazıyor" sorusunun cevabı
     * hafızadan değil arayüz kataloğundan gelir ve bir etiket değiştiğinde
     * yardım sessizce yanlış kalmaz: bu kapı kırılır.
     */
    #[DataProvider('articleSlugs')]
    public function test_every_screen_name_the_article_quotes_exists_in_the_interface(string $slug): void
    {
        $labels = self::interfaceLabels();

        preg_match_all('#<strong>(.*?)</strong>#s', $this->article($slug), $quoted);

        foreach ($quoted[1] as $raw) {
            $label = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($raw), ENT_QUOTES)));

            if ($label === '') {
                continue;
            }

            self::assertContains(
                $label,
                $labels,
                "HELP-CENTRE-REAL-LABELS-06: [{$slug}] “{$label}” diyor ama arayüz kataloğunda böyle bir ad yok. "
                .'Ekran adları ÖLÇÜLÜR (`lang/po/*.en.po`), hafızadan yazılmaz.'
            );
        }
    }

    // --- HELP-CENTRE-NO-BREAKPOINT-07 -------------------------------------

    /**
     * Yardım makalesi TELEFONDA okunur.
     *
     * Dar ekran taban, geniş ekran ilerleyici zenginleştirmedir
     * (`docs/118` E1). Bir kırılma noktası sınıfı, düzeni geniş ekranda
     * karara bağlayıp dar ekrana kırpılmış hâlini bırakır.
     */
    #[DataProvider('articlePaths')]
    public function test_no_article_is_breakpoint_gated(string $path): void
    {
        preg_match_all('/class="([^"]*)"/', $this->get($path)->getContent(), $matches);

        foreach ($matches[1] as $classList) {
            self::assertDoesNotMatchRegularExpression(
                '/(^|\s)(sm|md|lg|xl|2xl):/',
                $classList,
                "HELP-CENTRE-NO-BREAKPOINT-07: [{$path}] kırılma noktası: {$classList}"
            );
        }
    }

    // --- HELP-CENTRE-SOURCE-LANGUAGE-08 -----------------------------------

    /**
     * KAYNAK DİLDE eksiksiz, başka dilde SESSİZCE eksik değil.
     *
     * Kaynak dil İngilizcedir (`docs/118` E4) ve çeviri kilidi sahibin açık
     * komutuna bağlıdır (`docs/107` Ufuk 30). Bu yüzden kapı iki şey ister:
     * (a) her makalenin kaynak dildeki dosyası VARDIR; (b) çevrilmiş
     * makaleler adıyla sayılıdır — bir makale çeviri listesindeyse o dilin
     * dosyası da var olmak zorundadır. Kalan makaleler kaynak dilde açılır
     * ve bunu okuyucuya SÖYLER; sessizce İngilizce göstermez.
     */
    #[DataProvider('articleSlugs')]
    public function test_every_article_exists_in_the_source_language(string $slug): void
    {
        self::assertFileExists(
            HelpLibrary::pathFor(HelpLibrary::SOURCE, $slug),
            "HELP-CENTRE-SOURCE-LANGUAGE-08: [{$slug}] kaynak dilde yok."
        );
    }

    public function test_a_translated_article_really_exists_in_that_language(): void
    {
        foreach (HelpLibrary::SUPPORTED as $locale) {
            foreach (HelpLibrary::TRANSLATED as $slug) {
                self::assertFileExists(
                    HelpLibrary::pathFor($locale, $slug),
                    "HELP-CENTRE-SOURCE-LANGUAGE-08: [{$locale}] için [{$slug}] çevirisi ilan edilmiş ama dosya yok."
                );
            }
        }
    }

    /**
     * Kaynak dilde açılan bir makale bunu SÖYLER.
     *
     * Türkçe okuyan biri İngilizce bir makaleye düştüğünde bunun bir hata
     * mı yoksa dilin öyle mi olduğunu bilmeli; sessiz bir yedek, okuyucuya
     * yardımın bozuk olduğunu düşündürür.
     */
    public function test_an_untranslated_article_says_it_is_in_the_source_language(): void
    {
        $untranslated = array_values(array_diff(HelpLibrary::ARTICLES, HelpLibrary::TRANSLATED));

        self::assertNotSame([], $untranslated, 'Bu kapı, çevrilmemiş en az bir makale varken anlamlı.');

        $html = $this->withHeaders(['Accept-Language' => 'tr'])
            ->get(HelpLibrary::pathOf($untranslated[0]))
            ->getContent();

        self::assertStringContainsString(
            'data-source-language-notice',
            $html,
            'HELP-CENTRE-SOURCE-LANGUAGE-08: çevrilmemiş makale, kaynak dilde olduğunu söylemiyor.'
        );
    }

    // --- Yardımcılar ------------------------------------------------------

    private function article(string $slug): string
    {
        return (string) file_get_contents(HelpLibrary::pathFor(HelpLibrary::SOURCE, $slug));
    }

    /**
     * Arayüzün İngilizce metinleri — ÖLÇÜLÜR, sayılmaz.
     *
     * @return list<string>
     */
    private static function interfaceLabels(): array
    {
        static $labels = null;

        if ($labels !== null) {
            return $labels;
        }

        $found = [];

        foreach ((array) glob(base_path('lang/po/*.en.po')) as $file) {
            $catalogue = (string) file_get_contents((string) $file);

            preg_match_all('#^msgstr\s+"((?:[^"\\\\]|\\\\.)*)"#m', $catalogue, $matches);

            foreach ($matches[1] as $value) {
                $text = stripcslashes($value);

                if (trim($text) !== '') {
                    $found[] = trim($text);
                }
            }
        }

        return $labels = array_values(array_unique($found));
    }
}
