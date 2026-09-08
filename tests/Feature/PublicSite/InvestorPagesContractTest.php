<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Support\Localization\SiteText;
use App\Support\Site\HomeStory;
use App\Support\Site\InvestorDossier;
use App\Support\Site\PublicPlans;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * YATIRIMCI SAYFALARININ SÖZLEŞMESİ — FF-251, `docs/149`.
 *
 * ── NEDEN BU KAPI VAR ────────────────────────────────────────────────────
 *
 * Sahibin isteği iki cümleydi: *"yatırımcı ilişkileri, pitch deck vb.
 * bilgiler için sayfalar olmalı"* ve *"tabii ki uydurulmuş rakamla değil"*.
 *
 * İkinci cümle bir niyet olarak yazılırsa altı ay dayanır. Bir yatırımcı
 * sayfası, üzerine sayı eklemek için en güçlü baskının olduğu yerdir: bir
 * gün biri "şimdilik bir tahmin koyalım" der ve o tahmin kalıcı olur.
 * Kimse bir pazarlama sayfasından bir satır silmeyi hatırlamaz; kırmızı bir
 * test hatırlatır. Ana sayfada `HOME-HONEST-08` tam bunun için var; burada
 * ölçü BİR ADIM DAHA İLERİ gidiyor.
 *
 * ── ÖLÇÜLEN ─────────────────────────────────────────────────────────────
 *
 *   · INVESTOR-HONEST-01 — sayfadaki HER rakam `InvestorDossier`den gelir.
 *     Yasak kelime listesi de var ama asıl kapı budur: bir yasak liste
 *     yalnız BİLDİĞİ uydurmayı yakalar, bu kural bilmediğini de yakalar.
 *   · INVESTOR-REAL-02  — parça ve sınır başlıkları ürünün kendi
 *     envanterinden gelir ve gerçekten çizilir.
 *   · INVESTOR-SCENE-03 — sahne dağarcığı var, sayfa başına tuval BİR,
 *     dar ekran bastırması yok.
 *   · INVESTOR-LINK-04  — sayfalar gezinti/altbilgi/sitemap zincirinde ve
 *     çıkan her iç bağlantı gerçekten açılıyor.
 *
 * ── ÖLÇÜLMEYEN ──────────────────────────────────────────────────────────
 *
 * Burada bir kare bile çizilmiyor: "320 pikselde taşıyor mu" sorusu jsdom'da
 * ya da PHP'de SORULAMAZ. Cevabı `scripts/mobile-ux-audit` içinde, gerçek
 * Chrome'da (`docs/146` §7).
 */
final class InvestorPagesContractTest extends TestCase
{
    /*
        VERİTABANI YALNIZ SİTEMAP İÇİN.

        Yatırımcı sayfalarının kendisi veritabanına dokunmadan çalışır ve
        çalışması gerekir (`ShowInvestorPageController`). Ama sitemap kütüğü
        OKUYOR (`ResolvePageDelivery`, FF-214) ve tablosu olmayan bir şemada
        o okuma patlar — yani buradaki tazeleme sayfaların bağımlılığı değil,
        sitemap iddiasının bedeli.
    */
    use RefreshDatabase;

    /** @var list<string> */
    private const PAGES = [
        '/investors',
        '/investors/product',
        '/investors/deck',
        '/investors/contact',
    ];

    /** @var list<string> */
    private const TEMPLATES = [
        'resources/views/public/investors/overview.blade.php',
        'resources/views/public/investors/product.blade.php',
        'resources/views/public/investors/deck.blade.php',
        'resources/views/public/investors/contact.blade.php',
        'resources/views/public/investors/partials/evidence.blade.php',
    ];

    private function html(string $uri): string
    {
        return (string) $this->get($uri)->getContent();
    }

    private function xpath(string $uri): DOMXPath
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$this->html($uri));
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($dom);
    }

    /**
     * Yalnız `<main>`in OKUNAN metni.
     *
     * Kabuk (üst çubuk, altbilgi, `<head>`) dışarıda: oradaki bir sayı bu
     * paketin iddiası değildir ve onu burada ölçmek, başka bir paketin
     * kararını bu kapıya bağlamak olurdu.
     */
    private function mainText(string $uri): string
    {
        $main = $this->xpath($uri)->query('//main');
        self::assertNotFalse($main);
        self::assertGreaterThan(0, $main->length, "[{$uri}] sayfasında `<main>` yok.");

        return (string) $main->item(0)?->textContent;
    }

    // --- INVESTOR-HONEST-01 : uydurma rakam yok ---------------------------

    /**
     * SAYFAYA GİREN HER RAKAM ÖLÇÜLMÜŞ BİR OLGUDAN GELİR.
     *
     * İzin verilen küme bir LİSTE değil, bir TÜREV: `InvestorDossier`in
     * kendi ürettiği dizelerdeki rakamlar. Yani kapı bir sayıyı beyaz listeye
     * almıyor, bir YOLU şart koşuyor — bir sayı sayfaya ancak ölçümden
     * geçerek girebilir.
     *
     * Bir gün biri katalog metnine "40 percent growth" ya da "2M market"
     * yazarsa, o rakam dosyadan geçmediği için burada kırılır.
     */
    public function test_every_number_on_the_page_comes_from_a_measured_fact(): void
    {
        /** @var InvestorDossier $dossier */
        $dossier = $this->app->make(InvestorDossier::class);

        /*
            Plan kataloğu ölçülmüş bir kaynaktır ama dosyanın DIŞINDADIR
            (`PublicPlans`, `PlanCatalogueSeeder`). Katalog okunamadığında
            liste boştur ve fiyat bölümü dürüst boş hâline düşer; ikisinde de
            sayfaya kaynaksız bir rakam girmez.
        */
        $prices = [];

        foreach ($this->app->make(PublicPlans::class)->forLocale('en') as $plan) {
            $prices[] = (string) ($plan['price'] ?? '');
            $prices[] = $plan['name'];
        }

        $allowed = $dossier->measuredNumerals('en', $prices);

        foreach (self::PAGES as $uri) {
            preg_match_all('/\d[\d.,]*/', $this->mainText($uri), $matches);

            $unmeasured = [];

            foreach ($matches[0] as $token) {
                $token = rtrim($token, '.,');

                if ($token !== '' && ! in_array($token, $allowed, true)) {
                    $unmeasured[$token] = $token;
                }
            }

            self::assertSame(
                [],
                array_values($unmeasured),
                "INVESTOR-HONEST-01: [{$uri}] sayfasında ölçülmemiş rakam var: "
                .implode(', ', $unmeasured)
                .' — bir sayı bu sayfalara ancak `InvestorDossier` üzerinden girebilir. '
                .'Yatırımcıya hazırlık uydurmayla değil, yapılmış işi ve bilinen sınırları '
                .'göstererek kurulur.'
            );
        }
    }

    /**
     * BİLİNEN UYDURMA DESENLERİ.
     *
     * Yukarıdaki kural bilmediğini de yakalar; bu liste, bilinenleri hata
     * mesajında ADIYLA söylemek için var. "Ölçülmemiş rakam" hatası bir
     * geliştiriciye ne yaptığını anlatır; "referans uydurulmuş" hatası
     * NEDEN yasak olduğunu anlatır.
     */
    public function test_the_pages_invent_no_proof_they_do_not_have(): void
    {
        foreach (self::PAGES as $uri) {
            $html = strtolower($this->html($uri));

            foreach ([
                'testimonial',
                'trusted by',
                'customers served',
                'award',
                'as seen in',
                'coming soon',
                'launching soon',
                'join thousands',
                'rated 5',
                'money-back',
                'case study',
                'backed by',
                'market leader',
                'fastest growing',
            ] as $claim) {
                self::assertStringNotContainsString(
                    $claim,
                    $html,
                    "INVESTOR-HONEST-01: [{$uri}] \"{$claim}\" taşıyor — bu depoda ölçülmeyen "
                    .'hiçbir kanıt bir yatırımcı sayfasına yazılamaz.'
                );
            }

            /*
                PAZAR BÜYÜKLÜĞÜ ÜÇLÜSÜ ve büyüme kısaltmaları. Kelime sınırı
                şart: "arr" bir kısaltmadır ama "narrow" içinde de geçer.
            */
            self::assertDoesNotMatchRegularExpression(
                '/\b(tam|sam|som|arr|mrr|cagr|roi)\b/i',
                $html,
                "INVESTOR-HONEST-01: [{$uri}] TAM/SAM/SOM ya da bir büyüme kısaltması taşıyor; "
                .'bu depoda hiçbiri ölçülemez.'
            );

            self::assertDoesNotMatchRegularExpression(
                '/total addressable market|serviceable (available|obtainable)/i',
                $html,
                "INVESTOR-HONEST-01: [{$uri}] kaynağı olmayan bir pazar büyüklüğü taşıyor."
            );

            /*
                "1000+ restoran" ailesi. Artı işareti arıyor: bir sürüm
                numarası ya da bir fiyat kırmaz.
            */
            self::assertDoesNotMatchRegularExpression(
                '/\b\d[\d.,]*\s*\+\s*(restaurant|customer|business|user|venue|table|place)/i',
                $html,
                "INVESTOR-HONEST-01: [{$uri}] sayılmamış bir müşteri sayısı ilan ediyor."
            );

            /*
                YÜZDE İŞARETİ. Bir yatırımcı sayfasındaki her yüzde bir
                orandır ve bu depoda ölçülen tek oran YOKTUR — hizmet seviyesi
                rakamı bile bilerek boş (`config/sla.php`). Sayfa oranı
                YAZMAZ, `/sla`ya bağlar.
            */
            self::assertDoesNotMatchRegularExpression(
                '/\d\s*%|%\s*\d/',
                $this->mainText($uri),
                "INVESTOR-HONEST-01: [{$uri}] bir yüzde taşıyor; bu depoda ölçülen bir oran yok."
            );
        }
    }

    /**
     * SAHTE LOGO VE CANLI SAYAÇ DA BİR İDDİADIR.
     *
     * Bir müşteri logosu bir `<img>`dir; bir canlı sayaç bir betiktir.
     * İkisi de sayfanın gövdesinde YOK ve olmayacak — biri eklendiği gün bu
     * kapı konuşur ve o gün birinin "bu kimin logosu, bu sayı nereden
     * geliyor?" diye sorması gerekir.
     */
    public function test_no_logo_and_no_live_counter_can_enter_the_body(): void
    {
        foreach (self::PAGES as $uri) {
            $xpath = $this->xpath($uri);

            foreach (['//main//img' => 'bir görsel', '//main//script' => 'bir betik', '//main//iframe' => 'bir çerçeve'] as $query => $what) {
                $found = $xpath->query($query);
                self::assertNotFalse($found);
                self::assertSame(
                    0,
                    $found->length,
                    "INVESTOR-HONEST-01: [{$uri}] gövdesinde {$what} var. Kaynağı ve iddiası "
                    .'sorulmadan bir logo, bir referans, bir ekran görüntüsü ya da bir canlı '
                    .'sayaç bu sayfalara giremez.'
                );
            }
        }
    }

    // --- INVESTOR-REAL-02 : iddia envanterden gelir -----------------------

    public function test_every_part_and_limit_named_here_comes_from_the_product_inventory(): void
    {
        $text = $this->app->make(SiteText::class);
        $html = $this->html('/investors/product');

        foreach ([HomeStory::CHAIN, HomeStory::PARTS, HomeStory::LIMITS] as $stems) {
            foreach ($stems as $stem) {
                $title = $text->get($stem.'.title', 'en');

                self::assertStringContainsString(
                    e($title),
                    $html,
                    "INVESTOR-REAL-02: envanterde olan \"{$title}\" yatırımcı sayfasında çizilmiyor."
                );
            }
        }
    }

    /**
     * VE HER İDDİANIN ARKASINDAKİ DOSYA GERÇEKTEN VAR.
     *
     * Bu, sayfanın en pahalı cümlesinin karşılığı: "iddialar bir dosyaya
     * bağlı". Bağlı olmadığı gün sayfa onu "bu dağıtımda yok" diye yazar —
     * ama kapı, o hâlin SESSİZCE normalleşmesini engeller.
     */
    public function test_the_claims_the_page_counts_as_proven_really_resolve_to_files(): void
    {
        /** @var InvestorDossier $dossier */
        $dossier = $this->app->make(InvestorDossier::class);
        $facts = $dossier->facts('en');

        $broken = [];

        foreach ([$facts['chain'], $facts['parts']] as $rows) {
            foreach ($rows as $row) {
                if ($row['source'] !== null && ! $row['present']) {
                    $broken[] = $row['source'];
                }
            }
        }

        self::assertSame(
            [],
            $broken,
            'INVESTOR-REAL-02: iddianın dayandığı dosya yok: '.implode(', ', $broken)
        );

        self::assertGreaterThan(
            0,
            $facts['counts']['sources'],
            'INVESTOR-REAL-02: tek bir iddia bile bir dosyaya bağlı değil — sayfanın '
            .'"kanıt" bölümü boş bir söz olurdu.'
        );

        self::assertGreaterThan(
            0,
            $facts['counts']['gates'],
            'INVESTOR-REAL-02: sayfada sayılan kapı betiklerinin hiçbiri depoda yok.'
        );
    }

    // --- INVESTOR-SCENE-03 : sahne var, tuval BİR, bastırma yok -----------

    public function test_each_page_carries_the_scene_and_exactly_one_canvas(): void
    {
        foreach (self::PAGES as $uri) {
            $xpath = $this->xpath($uri);

            $prologue = $xpath->query('//*[contains(concat(" ", @class, " "), " site-prologue ")]');
            self::assertNotFalse($prologue);
            self::assertGreaterThan(
                0,
                $prologue->length,
                "INVESTOR-SCENE-03: [{$uri}] önsöz bandını çizmiyor — sayfa sitenin geri "
                .'kalanından başka bir ürün gibi görünür.'
            );

            /*
                SAYFA BAŞINA TEK TUVAL (`SAHNE-B7`). İkinci bir WebGL
                bağlamının maliyeti hâlâ ÖLÇÜLMEDİ (`docs/146` §11 madde 1);
                ölçülmemiş bir maliyet ürüne sokulmaz.
            */
            $canvases = $xpath->query('//canvas[@data-scene="field"]');
            self::assertNotFalse($canvases);
            self::assertSame(
                1,
                $canvases->length,
                "INVESTOR-SCENE-03: [{$uri}] {$canvases->length} tuval taşıyor."
            );
        }
    }

    public function test_no_investor_rule_is_written_for_a_wide_screen_and_undone_on_a_narrow_one(): void
    {
        foreach (self::TEMPLATES as $relative) {
            $template = (string) file_get_contents(base_path($relative));

            preg_match_all('/class="([^"]*)"/', $template, $matches);

            foreach ($matches[1] as $classList) {
                self::assertDoesNotMatchRegularExpression(
                    '/(^|\s)(sm|md|lg|xl|2xl|max-sm|max-md|max-lg|max-xl|max-2xl):/',
                    $classList,
                    "INVESTOR-SCENE-03: [{$relative}] bir kırılma noktası varyantı taşıyor: {$classList}"
                    .' — taban 320 pikseldir ve geniş ekran onun ÜSTÜNE eklenir.'
                );

                self::assertDoesNotMatchRegularExpression(
                    '/(^|\s)hidden(\s|$)/',
                    $classList,
                    "INVESTOR-SCENE-03: [{$relative}] \"dar ekranda gizle\" taşıyor: {$classList}"
                    .' — gizlenen şey yine indirilir, yine odaklanılabilir, yine bakım ister.'
                );
            }
        }
    }

    /**
     * BETİKSİZ SAYFA EKSİKSİZ.
     *
     * Sunucu HTML'i tek başına tam: envanterin her satırı, her bağlantı ve
     * her kanıt cümlesi betik çalıştırmayan bir botta da okunur. Sahne bunun
     * ÜSTÜNE eklenir (`docs/118` E8).
     */
    public function test_the_body_is_complete_without_any_script(): void
    {
        $html = $this->html('/investors');

        foreach (['ground-rules', 'chain', 'built', 'limits', 'commitment', 'infrastructure', 'contact'] as $section) {
            self::assertStringContainsString(
                'id="'.$section.'"',
                $html,
                "INVESTOR-SCENE-03: `{$section}` bölümü sunucu gövdesinde yok."
            );
        }

        self::assertGreaterThan(
            5000,
            strlen($html),
            'INVESTOR-SCENE-03: gövde boş bir kabuğa dönmüş olabilir.'
        );
    }

    // --- INVESTOR-LINK-04 : zincire bağlı, 404 bırakmıyor -----------------

    public function test_the_pages_answer_and_are_reachable_from_the_shell(): void
    {
        foreach (self::PAGES as $uri) {
            $this->get($uri)->assertOk();
        }

        // Altbilgi her kurumsal adreste çizilir; giriş sayfası oradan açılır.
        self::assertStringContainsString(
            'href="/investors"',
            $this->html('/'),
            'INVESTOR-LINK-04: ana sayfanın kabuğunda yatırımcı ilişkilerine bağlantı yok — '
            .'yazılmış ama hiçbir yerden açılamayan bir sayfa, yok sayılır.'
        );

        $sitemap = (string) $this->get('/sitemap.xml')->getContent();

        foreach (self::PAGES as $uri) {
            self::assertStringContainsString(
                '<loc>'.rtrim((string) config('app.url'), '/').$uri.'</loc>',
                $sitemap,
                "INVESTOR-LINK-04: [{$uri}] sitemap'te yok."
            );
        }
    }

    /**
     * SAYFALARIN VERDİĞİ HER İÇ BAĞLANTI GERÇEKTEN AÇILIYOR.
     *
     * Elle yazılmış bir bağlantı, hedefi bir gün taşındığında sessizce 404'e
     * döner ve kimse fark etmez — çünkü sayfa yine açılır. Bağlantının
     * varlığı, arkasındaki sayfanın çalıştığı İDDİASIDIR.
     */
    public function test_no_investor_page_leaves_a_link_that_answers_404(): void
    {
        $targets = [];

        foreach (self::PAGES as $uri) {
            $links = $this->xpath($uri)->query('//main//a/@href');
            self::assertNotFalse($links);

            foreach ($links as $link) {
                $href = $link->nodeValue ?? '';

                // Yalnız kendi sitemizin yolları; çıpa bir sayfa değildir.
                if (! str_starts_with($href, '/') || str_starts_with($href, '//')) {
                    continue;
                }

                $targets[strtok($href, '#')] = true;
            }
        }

        self::assertNotSame([], $targets, 'INVESTOR-LINK-04: sayfalarda hiç iç bağlantı yok — ölçüm dayanaksız.');

        foreach (array_keys($targets) as $target) {
            self::assertTrue(
                $this->get($target)->isSuccessful(),
                "INVESTOR-LINK-04: [{$target}] açılmıyor; yatırımcı sayfası çalışmayan bir "
                .'adrese bağlanıyor.'
            );
        }
    }
}
