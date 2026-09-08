<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Domain\Content\Block\BlockType;
use App\Domain\Content\PagePublicationStatus;
use App\Models\ContentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CONTENT-TEMPLATE-01…10 — on sekiz sayfanın çizildiği TEK şablon (`docs/148`).
 *
 * `CorporateProductPageTest` şablonun ne SÖYLEDİĞİNİ ölçüyor: doğru metin,
 * doğru sıra, doğru şema. Bu test şablonun nasıl GÖRÜNDÜĞÜNÜ ölçer — daha
 * doğrusu, görünüşün dayandığı yapıyı.
 *
 * Neden ayrı bir dosya: bir sayfayı değil on sekizini birden çizen bir
 * şablonda "şu bloğun görünümü" diye bir şey yoktur. Bir blok türünün görsel
 * işi değişirse on sekiz sayfa birden değişir, ve o işin ne olduğu tek yerde
 * yazılı olmalı. Aşağıdaki iddialar o yazının kod tarafındaki karşılığıdır.
 *
 * Ölçüm SUNUCU çıktısında yapılır. Bir blok türünün kimliği (`data-block`)
 * ilk HTML yanıtında bulunmalı, çünkü hem CSS hem de bu test onu oradan
 * okuyor; betikle sonradan yazılan bir sınıf ikisini de kandırırdı.
 */
final class CorporatePageTemplateTest extends TestCase
{
    use RefreshDatabase;

    private const STYLESHEET = 'resources/css/site-content.css';

    private function publishedQrMenu(): void
    {
        foreach ([
            ['urun', '/en/product/', PagePublicationStatus::Planned, null, 'Product overview'],
            ['urun.qr-menu', '/en/product/qr-menu/', PagePublicationStatus::Published, 'urun', 'QR menu'],
        ] as [$key, $path, $status, $parent, $title]) {
            ContentPage::query()->create([
                'page_key' => $key,
                'locale' => 'en',
                'canonical_path' => $path,
                'content_type' => 'urun',
                'template_key' => 'urun',
                'parent_key' => $parent,
                'title' => $title,
                'priority' => 'P0',
                'publication_status' => $status->value,
                'was_ever_published' => $status->isPublished(),
            ]);
        }
    }

    private function body(): string
    {
        $this->publishedQrMenu();

        $html = (string) $this->get('/en/product/qr-menu/')->getContent();

        self::assertSame(1, preg_match('#<main\b.*?</main>#s', $html, $main));

        return $main[0];
    }

    private function stylesheet(): string
    {
        return (string) file_get_contents(base_path(self::STYLESHEET));
    }

    /**
     * CONTENT-TEMPLATE-01 — her blok türü KENDİ kimliğiyle çizilir.
     *
     * Şablonun bütün mesele bu: on blok türü on ayrı okuma işi yapıyor ve
     * aynı görünmemeleri gerekiyor. Görünüm CSS'te yaşıyor, ama CSS'in
     * tutunacağı bir kanca gerekiyor — ve o kanca sınıf adı DEĞİL, blok
     * türünün kendisi olmalı: `BlockType` bir gün büyürse eksik kalan kanca
     * burada görünür, ekranda değil.
     */
    public function test_every_block_type_renders_under_its_own_identity(): void
    {
        $main = $this->body();

        foreach (BlockType::requiredForProductPage() as $type) {
            self::assertSame(
                1,
                substr_count($main, 'data-block="'.$type->value.'"'),
                "CONTENT-TEMPLATE-01: {$type->value} bloğu kendi kimliğiyle bir kez çizilmedi.",
            );
        }
    }

    /**
     * CONTENT-TEMPLATE-02 — doğrudan cevap H1'in hemen ardındaki GİRİŞ'tir.
     *
     * Kendi başlığı yok (§15) ve bir bölüm de değil: sayfanın ilk ekranında
     * cevabı bir `section` kabuğuna sarmak, ekran okuyucuya olmayan bir
     * bölüm ilan etmek olurdu.
     */
    public function test_the_direct_answer_is_the_lede_right_after_the_single_h1(): void
    {
        $main = $this->body();

        self::assertSame(1, preg_match(
            '#</h1>\s*<p[^>]*data-block="direct_answer"#',
            $main,
        ), 'CONTENT-TEMPLATE-02: doğrudan cevap H1\'in hemen ardında bir paragraf olarak durmuyor.');

        self::assertStringNotContainsString('<section data-block="direct_answer"', $main);
    }

    /**
     * CONTENT-TEMPLATE-03 — adım listesi NUMARAYI ELLE YAZMAZ.
     *
     * Numara CSS sayacından gelir. Elle yazılmış bir "1." listeyi yeniden
     * sıralayan kişinin güncellemeyi unutmasına açıktır — ve o gün numara
     * ile sıra birbirini yalanlar. Liste yine `ol`dur: sıra bilginin
     * kendisidir ve `list-style: none` onu ekran okuyucudan almasın diye
     * `role="list"` açıkça yazılır (Safari'nin ölçülmüş davranışı).
     */
    public function test_the_steps_are_an_ordered_list_whose_numbers_are_not_typed_by_hand(): void
    {
        $main = $this->body();

        self::assertSame(1, preg_match(
            '#<section[^>]*data-block="how_it_works"[^>]*>(.*?)</section>#s',
            $main,
            $block,
        ));

        self::assertSame(1, preg_match(
            '#<ol[^>]*role="list"#',
            $block[1],
        ), 'CONTENT-TEMPLATE-03: adım listesi rolü ilan edilmiş bir `ol` değil.');

        // Numara işaretlemede YOK; sayaç CSS'te.
        self::assertSame(0, preg_match('#>\s*\d+[.)]\s*<#', $block[1]));
        self::assertStringContainsString('counter(', $this->stylesheet());
    }

    /**
     * CONTENT-TEMPLATE-04 — gereksinimler TABLO olarak kalır.
     *
     * Dar ekranda satır iki kolona bölünmüyor, alt alta iniyor — ama bu bir
     * ÇİZİM kararıdır ve anlamı değiştirmez. `display` değiştiği anda
     * tarayıcı tablo rollerini düşürür; bu yüzden roller AÇIKÇA yazılır.
     * Yazılmasaydı "cevap sistemleri tabloyu tablo olarak okur" cümlesi
     * sessizce yalan olurdu.
     */
    public function test_the_requirements_stay_a_table_even_after_the_paint_changes(): void
    {
        $main = $this->body();

        self::assertSame(1, preg_match('#<table[^>]*role="table"#', $main));
        self::assertStringContainsString('role="rowgroup"', $main);
        self::assertStringContainsString('role="row"', $main);
        self::assertStringContainsString('role="rowheader"', $main);
        self::assertStringContainsString('role="cell"', $main);
        // Kendi içinde yatay kaydırılan bir tablo artık yok: satır kırılıyor.
        self::assertStringNotContainsString('overflow-x-auto', $main);
    }

    /**
     * CONTENT-TEMPLATE-05 — SINIRLAR gizlenmez.
     *
     * Sayfanın en dürüst bölümü katlanmaz, açılır kapanır bir kutuya
     * konmaz ve `hidden` almaz. Bir ziyaretçi "ne yapamıyor" sorusunu
     * sormadan cevabı görmelidir; tık ardına konan bir dürüstlük,
     * dürüstlük değildir.
     */
    public function test_the_limitations_are_never_folded_away(): void
    {
        $main = $this->body();

        self::assertSame(1, preg_match(
            '#<section[^>]*data-block="limitations"[^>]*>(.*?)</section>#s',
            $main,
            $block,
        ));

        self::assertStringNotContainsString('<details', $block[0]);
        self::assertStringNotContainsString('hidden', $block[0]);
        self::assertStringContainsString('No sizes, portions or extras', $block[0]);
    }

    /**
     * CONTENT-TEMPLATE-06 — SINIRLAR korkutmaz.
     *
     * Gizlememek ile alarm çalmak arasındaki fark bir renk kararıdır:
     * kırmızı bir kutu ziyaretçiye "burada bir arıza var" der, oysa burada
     * yazan şey ürünün bilinçli kapsamıdır. Bu yüzden bölüm hata/uyarı
     * jetonlarına HİÇ dokunmaz; ayrımını yüzey ve kenarlıkla kurar.
     */
    public function test_the_limitations_do_not_borrow_the_alarm_palette(): void
    {
        $css = $this->stylesheet();

        self::assertSame(1, preg_match('#\.site-doc-limits\b.*?(?=\n\.site-doc-(?!limits)|\z)#s', $css, $rules));

        foreach (['--fg-danger', '--surface-danger', '--border-danger', '--fg-warning', '--surface-warning'] as $alarm) {
            self::assertStringNotContainsString(
                $alarm,
                $rules[0],
                "CONTENT-TEMPLATE-06: sınırlar bölümü alarm jetonu `{$alarm}` kullanıyor.",
            );
        }
    }

    /**
     * CONTENT-TEMPLATE-07 — SSS betiksiz katlanır ve cevabı HTML'de durur.
     *
     * `details`/`summary` tarayıcının kendi açılır kapanır düğmesidir:
     * klavyeyle çalışır, durumunu ekran okuyucuya söyler, betik olmadan
     * açılır (`docs/118` E8). Katlanan şey yalnız GÖRÜNÜM: cevap ilk HTML
     * yanıtında zaten var, yani `FAQPage` işaretlemesi görünmeyen bir bilgi
     * ilan etmiyor (§14) ve betiksiz bir bot da onu okuyor.
     */
    public function test_the_faq_folds_without_script_and_still_ships_its_answers(): void
    {
        $main = $this->body();

        self::assertSame(1, preg_match(
            '#<section[^>]*data-block="faq"[^>]*>(.*?)</section>#s',
            $main,
            $block,
        ));

        self::assertStringContainsString('<details', $block[0]);
        self::assertStringContainsString('<summary', $block[0]);
        // Soru hâlâ bir BAŞLIK: ekran okuyucu kullanıcısı sayfayı
        // başlıklarla gezer ve katlamak o gezintiyi bozmamalı.
        self::assertSame(1, preg_match('#<summary[^>]*>\s*<h3#', $block[0]));
        // Cevap kapalıyken de sunucudan geliyor.
        self::assertStringContainsString('The code opens a web page in the browser', $block[0]);
        self::assertStringNotContainsString('<script', $block[0]);
    }

    /**
     * CONTENT-TEMPLATE-08 — kırıntı ilk ekranı YEMEZ ama dokunulabilir.
     *
     * İki kural aynı anda tutulmak zorunda: bağlantı 44 pikselden kısa
     * olamaz (`docs/117` K1) ve kırıntı sarıp üç satıra çıkarak başlığı
     * ekranın dışına itemez (`TOUCH-FIRST-INTERFACE` madde 3). Tek çözüm
     * SARMAMAK: tek satır kalır, sığmazsa kendi içinde kayar.
     */
    public function test_the_breadcrumb_stays_one_touchable_row(): void
    {
        $main = $this->body();

        self::assertStringContainsString('site-doc-trail', $main);

        $css = $this->stylesheet();

        self::assertSame(1, preg_match('#\.site-doc-trail\s*\{(.*?)\}#s', $css, $rules));
        self::assertStringContainsString('flex-wrap: nowrap', $rules[1]);
        self::assertStringContainsString('overflow-x: auto', $rules[1]);

        self::assertSame(1, preg_match('#\.site-doc-trail-link\s*\{(.*?)\}#s', $css, $link));
        self::assertStringContainsString('var(--control-height)', $link[1]);
    }

    /**
     * CONTENT-TEMPLATE-09 — gövde hâlâ kendi ikonunu ÇİZMEZ.
     *
     * `docs/136` §4 ICON-04. Şablon zenginleşti ama ailenin dışına çıkmadı:
     * SSS'in açılır kapanır işareti bir ikon değil, düğmenin KENDİ
     * geometrisidir ve CSS ile çizilir. Emoji her yerde yasak.
     */
    public function test_the_body_still_draws_no_icon_of_its_own(): void
    {
        $main = $this->body();

        self::assertStringNotContainsString('<svg', $main);
        self::assertStringNotContainsString('<i class', $main);
        self::assertSame(
            0,
            preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $main),
            'CONTENT-TEMPLATE-09: gövdede emoji var.',
        );
    }

    /**
     * CONTENT-TEMPLATE-10 — şablonun stil dosyası SİSTEMİN içinde durur.
     *
     * `docs/136` §9'un yasak listesi: ham renk yok, kırılma noktası yok,
     * `!important` yok, ham süre/yumuşatma yok. Ve dosya `app.css`ten
     * gerçekten çağrılıyor — çağrılmayan bir stil dosyası hiçbir şeyi
     * kırmaz, yalnız sessizce hiçbir şey yapmaz.
     */
    public function test_the_stylesheet_writes_no_raw_colour_no_breakpoint_and_no_important(): void
    {
        $css = $this->stylesheet();
        // Yorumlar ölçüm dışı: gerekçe metni bir kural değildir.
        $rules = (string) preg_replace('#/\*.*?\*/#s', '', $css);

        self::assertStringContainsString(
            "@import './site-content.css';",
            (string) file_get_contents(base_path('resources/css/app.css')),
            'CONTENT-TEMPLATE-10: stil dosyası `app.css` tarafından çağrılmıyor.',
        );

        foreach (['#', 'rgb(', 'rgba(', 'hsl(', 'oklch(', '!important', '@media (min-width', '@media (max-width'] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $rules,
                "CONTENT-TEMPLATE-10: stil dosyasında `{$forbidden}` var.",
            );
        }

        /*
            Ham süre/yumuşatma yok: hareket jetonlardan okunur (`docs/136` §7).
            Ölçüm `transition`/`animation` bildirimiyle SINIRLI — `linear`
            sözcüğü `linear-gradient` içinde de geçer ve bir gradyan bir
            zamanlama eğrisi değildir.
        */
        self::assertSame(0, preg_match('/\b(transition|animation)\b[^;]*\b\d+m?s\b/', $rules));
        self::assertSame(0, preg_match('/\b(transition|animation)\b[^;]*\b(ease|ease-in|ease-out|ease-in-out|linear)\b/', $rules));

        // Hareket YALNIZ açıkça istendiğinde doğar — tek yönlü kapı.
        if (str_contains($rules, 'transition')) {
            self::assertStringContainsString('@media (prefers-reduced-motion: no-preference)', $rules);
            self::assertStringNotContainsString('prefers-reduced-motion: reduce', $rules);
        }
    }
}
