<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Application\Legal\Port\LegalLibraryPort;
use App\Domain\Content\PagePublicationStatus;
use App\Models\ContentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FOOTER-LAYER-01…06 ve HEADER-ACTIONS-01…02 — masterpage'in KATMANLARI.
 *
 * ── Sahibin isteği ve ölçülen eksik ──────────────────────────────────────
 *
 * İstek (2026-09-08): *"Çoook zengin bir footer olmalı ve çok katmanlı olmalı
 * ve çok row olmalı ve çok menu grubu olmalı."* Ve: *"Üst çubuk da olgunlaşsın:
 * marka, gezinti, hesap eylemleri."*
 *
 * FF-232 zenginliğin KAYNAĞINI doğru kurmuştu — bağlantılar kütükten türüyor
 * ve boş grup çizilmiyor — ama YAPIYI kurmamıştı: tek bir bağlantı ızgarası
 * ve bir alt satır vardı. Sahip bunu gördü: *"iki sütunluk bir footer."*
 *
 * ── Bu testin ölçtüğü ───────────────────────────────────────────────────
 *
 * Estetiği DEĞİL — yapıyı ve dürüstlüğü. Kaç satır var, her satırın bir işi
 * var mı, on üç yasal belgenin hepsi bulunabiliyor mu, uzun bir grup dar
 * ekranda katlanıyor mu, ve katlamanın GİZLEMEYE dönüşmediği — yani her
 * adresin betiksiz gövdede durduğu.
 *
 * Kullanıcı yolculuğu: bir kurumsal alıcının hukuk birimi "Data Processing
 * Agreement"ı arar. Dün o belge yazılmıştı, adresi 200 dönüyordu ve
 * altbilgide HİÇ YOKTU — yani onu arayan kişi için yoktu. Bugün yasal satırda,
 * kendi başlığı altında duruyor ve on üçünün hiçbiri unutulamıyor: liste
 * `LegalLibraryPort::KEYS` ile karşılaştırılıyor.
 */
final class ShellLayersTest extends TestCase
{
    use RefreshDatabase;

    // --- FOOTER-LAYER-01 -------------------------------------------------------

    public function test_the_footer_is_built_from_rows_and_each_row_has_a_job(): void
    {
        $footer = $this->footer('/pricing');

        /*
            BUGÜN BEŞ SATIR ÇİZİLİYOR: marka, bağlantı grupları, yasal,
            kurumsal kimlik, alt satır. Altıncısı (pSEO bandı) kütükte
            yayınlanmış sayfa olmadığı için HİÇ çizilmiyor — boş bir başlık,
            olmayan bir bölümün sözünü vermektir (FOOTER-CONTENT-01).
        */
        self::assertSame(
            5,
            substr_count($footer, 'site-footer-row'),
            'FOOTER-LAYER-01: altbilginin satır sayısı beklenenden farklı.'
        );

        foreach ([
            'site-footer-brand' => 'marka satırı',
            'site-footer-grid' => 'bağlantı grupları satırı',
            'site-footer-legal' => 'yasal satırı',
            'site-footer-identity' => 'kurumsal kimlik satırı',
            'site-footer-bottom' => 'alt satır',
        ] as $marker => $job) {
            self::assertStringContainsString(
                $marker,
                $footer,
                "FOOTER-LAYER-01: {$job} çizilmemiş."
            );
        }

        /*
            ÇOK GRUP: bağlantı grupları satırı bugün üç grup taşıyor ve üçü de
            farklı bir soruyu yanıtlıyor. Kütükten türeyen gruplar bunların
            ÜSTÜNE gelir (FOOTER-CONTENT-04).
        */
        foreach (['product', 'company', 'account', 'legal'] as $group) {
            self::assertStringContainsString(
                'data-nav-group="'.$group.'"',
                $footer,
                "FOOTER-LAYER-01: [{$group}] grubu altbilgide yok."
            );
        }
    }

    public function test_publishing_a_page_adds_the_sixth_row_without_a_code_change(): void
    {
        $this->publishedPage();

        self::assertSame(
            6,
            substr_count($this->footer('/pricing'), 'site-footer-row'),
            'FOOTER-LAYER-01: yayına alınan sayfa pSEO satırını doğurmadı.'
        );
    }

    // --- FOOTER-LAYER-02 -------------------------------------------------------

    public function test_every_legal_document_the_repository_publishes_is_reachable_from_the_footer(): void
    {
        /*
            LİSTE ELLE YAZILMIYOR, KÜTÜPHANEDEN OKUNUYOR.

            FF-232'de altbilgide sekiz belge vardı; kütüphanede on üç. Beşi —
            ticari ileti izni, veri işleme, hizmet seviyesi, kabul edilebilir
            kullanım ve üçüncü taraf lisansları — yazılmış, rotası açılmış ve
            200 dönüyordu ama sitede hiçbir yerden BULUNAMIYORDU. Bir kapı
            "bugün sekiz tane var" diye sabitlense, on dördüncü belge
            eklendiği gün yine sessizce kaybolurdu.
        */
        $footer = $this->footer('/pricing');

        self::assertCount(13, LegalLibraryPort::KEYS, 'Belge sayısı değişti — kapıyı gözden geçirin.');

        foreach (LegalLibraryPort::KEYS as $key) {
            self::assertStringContainsString(
                'href="/'.$key.'"',
                $footer,
                "FOOTER-LAYER-02: [{$key}] yasal belgesi altbilgide yok. "
                .'Yazılmış ama bulunamayan bir sözleşme, onu arayan kişi için YOKTUR.'
            );

            $status = $this->get('/'.$key)->getStatusCode();

            self::assertSame(
                200,
                $status,
                "FOOTER-LAYER-02: [/{$key}] {$status} döndü."
            );
        }
    }

    // --- FOOTER-LAYER-03 -------------------------------------------------------

    public function test_a_long_group_starts_folded_and_a_short_one_starts_open(): void
    {
        /*
            KATLAMA GRUBUN BOYUNA BAĞLIDIR, EKRANIN GENİŞLİĞİNE DEĞİL.

            Yasal satırın on üç bağlantısı var: 320 pikselde her satır 44
            piksel, yani tek başına 572 piksel — 320×480'de bir ekrandan uzun.
            Ürün grubunun iki bağlantısı var ve onu katlamak bir dokunuşu iki
            dokunuşa çevirip hiçbir yer kazandırmazdı.

            Aynı karar her genişlikte aynıdır (`MP-05`): medya sorgusuyla
            takas edilen ikinci bir davranış YOK.
        */
        $footer = $this->footer('/pricing');

        self::assertMatchesRegularExpression(
            '#data-nav-group="legal"[^>]*>\s*<details class="site-footer-fold"\s*>#',
            $footer,
            'FOOTER-LAYER-03: on üç maddelik yasal grup AÇIK başlıyor — dar ekranda altbilgi bir ekrandan uzar.'
        );

        self::assertMatchesRegularExpression(
            '#data-nav-group="product"[^>]*>\s*<details class="site-footer-fold"\s+open\s*>#',
            $footer,
            'FOOTER-LAYER-03: iki maddelik ürün grubu KAPALI başlıyor — kazanç yok, dokunuş var.'
        );
    }

    // --- FOOTER-LAYER-04 -------------------------------------------------------

    public function test_the_shell_never_hides_anything_by_screen_width(): void
    {
        /*
            "MOBİLDE GİZLE" YASAK (`docs/118` E1, global TOUCH-FIRST-INTERFACE).

            Gizlenen şey yine indirilir, yine odaklanılabilir ve yine bakım
            ister; üstelik iki farklı ekranda iki farklı ürün doğurur. Kabuk
            dar ekranı TABAN alır ve geniş ekran onun üstüne eklenir —
            dolayısıyla bir `max-width` medya sorgusu, yazım sırasının ters
            döndüğünün kanıtıdır.

            `min-width` serbesttir ve aynı sebeple: taban koşulsuz yazılır,
            zenginleştirme üstüne eklenir.
        */
        $css = (string) file_get_contents(resource_path('css/site-shell.css'));

        preg_match_all('#@media([^{]+)\{#', $css, $matches);

        $offenders = array_values(array_filter(
            array_map('trim', $matches[1]),
            static fn (string $query): bool => str_contains($query, 'max-width'),
        ));

        self::assertSame(
            [],
            $offenders,
            'FOOTER-LAYER-04: kabuk bir `max-width` sorgusuyla bastırıyor: '.implode(' · ', $offenders)
        );
    }

    // --- FOOTER-LAYER-05 -------------------------------------------------------

    public function test_the_seller_identity_is_on_every_corporate_page_and_says_what_is_missing(): void
    {
        /*
            GİRİLMEMİŞ ALAN ATLANMAZ, "GİRİLMEDİ" DİYE YAZILIR.

            Atlanan bir satır, o alanın hiç istenmediği izlenimi verirdi;
            uydurma bir değer ise sözleşmenin tarafını yanlış gösterirdi.
            Karar `CompanyIdentity`de zaten verilmişti; bu kapı onun
            altbilgide de geçerli olduğunu donduruyor.
        */
        foreach (['/pricing', '/help', '/terms'] as $path) {
            $footer = $this->footer($path);

            self::assertStringContainsString(
                'data-company-identity=',
                $footer,
                "FOOTER-LAYER-05: [{$path}] altbilgisinde satıcı kimliği yok."
            );

            foreach (['legal_name', 'address', 'mersis', 'tax_office', 'tax_number', 'email', 'phone'] as $field) {
                self::assertStringContainsString(
                    'data-company-field="'.$field.'"',
                    $footer,
                    "FOOTER-LAYER-05: [{$field}] alanı altbilgide atlanmış."
                );
            }
        }
    }

    // --- FOOTER-LAYER-06 -------------------------------------------------------

    public function test_every_folded_link_is_still_in_the_scriptless_body(): void
    {
        /*
            KATLAMAK GİZLEMEK DEĞİLDİR (`docs/118` E8: taban HTML, tavan
            serbest). Katlanmış bir `<details>`in içeriği belgede DURUR:
            arama motoru, betiği engellenmiş ziyaretçi ve JavaScript
            çalıştırmayan AI botları hepsini görür.

            Ölçüm yasal satırla yapılır çünkü katlanan tek satır odur — ve
            tam olarak orada bir gizleme, on üç sözleşmeyi indekslenemez
            yapardı.
        */
        $withoutScripts = (string) preg_replace(
            '#<script\b.*?</script>#s',
            '',
            (string) $this->get('/pricing')->assertOk()->getContent()
        );

        foreach (LegalLibraryPort::KEYS as $key) {
            self::assertStringContainsString(
                'href="/'.$key.'"',
                $withoutScripts,
                "FOOTER-LAYER-06: [/{$key}] betiksiz gövdede yok — katlama gizlemeye dönüşmüş."
            );
        }
    }

    // --- HEADER-ACTIONS-01 -----------------------------------------------------

    public function test_the_primary_account_action_sits_in_the_bar_not_behind_the_menu(): void
    {
        /*
            Sahibin isteği: çubuk marka, gezinti ve HESAP EYLEMLERİ taşısın.
            FF-232'de eylemlerin ikisi de açılır bölmenin içindeydi:
            kaydolmaya karar vermiş biri, o kararı uygulamak için önce bir
            menü açmak zorundaydı.

            Çubuğa BİR eylem çıktı, ikisi değil — ve bu bir tercih değil bir
            ölçüm sonucu (`docs/138` §4): iki düğme 320 pikselde çubuğu üç
            satıra çıkarıyor, 157 piksel yer kaplıyordu. Çubukta kalan,
            siteyi ilk kez açan kişinin ihtiyacı olandır.

            Ölçüm, eylemin bölmenin DIŞINDA olduğunu arar: bölme açılmadan da
            görünür durumda mı?
        */
        $header = $this->header('/pricing');

        $panelStart = strpos($header, '<details');
        self::assertNotFalse($panelStart, 'Kabukta açılır bölme yok — ölçüm dayanaksız.');

        $beforePanel = substr($header, 0, $panelStart);

        self::assertStringContainsString(
            'href="/register"',
            $beforePanel,
            'HEADER-ACTIONS-01: birincil hesap eylemi hâlâ menünün içinde — '
            .'bir dönüşüm eyleminin önünde bir dokunuş var.'
        );

        self::assertStringNotContainsString(
            'href="/login"',
            $beforePanel,
            'HEADER-ACTIONS-01: ikinci eylem de çubuğa çıkmış — 320 pikselde çubuk üç satıra çıkar.'
        );

        self::assertStringContainsString(
            'href="/login"',
            $header,
            'HEADER-ACTIONS-01: oturum açma bağlantısı üst çubuktan tamamen kaybolmuş.'
        );
    }

    // --- HEADER-ACTIONS-02 -----------------------------------------------------

    public function test_no_account_link_is_written_twice_in_the_same_bar(): void
    {
        /*
            AYNI ADRES ÜST ÇUBUKTA İKİ KEZ YAZILMAZ: iki bağlantı aynı yere
            götürürse ziyaretçi hangisinin doğru olduğunu bilemez ve ekran
            okuyucu aynı hedefi iki kez okur.

            Altbilgideki kopya AYRI bir yerdir ve bilerek vardır: uzun bir
            sayfayı sonuna kadar okumuş kişi, üst çubuğun yüzlerce piksel
            yukarısındadır.
        */
        $header = $this->header('/pricing');

        foreach (['/login', '/register'] as $target) {
            self::assertSame(
                1,
                substr_count($header, 'href="'.$target.'"'),
                "HEADER-ACTIONS-02: [{$target}] üst çubukta bir kereden çok yazılmış."
            );
        }

        // Gezinti bölmesi hâlâ gerçek gezintiyi taşıyor — eylem dışarı
        // çıkarken menü boşalmadı.
        self::assertStringContainsString('aria-label="Primary"', $header);
        self::assertStringContainsString('site-menu-heading', $header);
    }

    // --- Yardımcılar -----------------------------------------------------------

    private function publishedPage(): void
    {
        ContentPage::query()->create([
            'page_key' => 'urun',
            'parent_key' => null,
            'locale' => 'en',
            'canonical_path' => '/en/product/',
            'content_type' => 'urun',
            'template_key' => 'urun',
            'title' => 'Product',
            'priority' => 'P0',
            'publication_status' => PagePublicationStatus::Published->value,
            'was_ever_published' => true,
        ]);
    }

    private function footer(string $path): string
    {
        return $this->fragment($path, 'footer');
    }

    private function header(string $path): string
    {
        return $this->fragment($path, 'header');
    }

    private function fragment(string $path, string $tag): string
    {
        $html = (string) $this->withHeaders(['Accept-Language' => 'en'])
            ->get($path)->assertOk()->getContent();

        preg_match("#<{$tag}\b.*?</{$tag}>#s", $html, $match);

        self::assertNotSame([], $match, "Sayfada [{$tag}] yok — ölçüm dayanaksız.");

        return $match[0];
    }
}
