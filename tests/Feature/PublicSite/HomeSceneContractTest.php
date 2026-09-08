<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Infrastructure\Content\Pages\ProductOverviewPage;
use App\Support\Localization\SiteText;
use App\Support\Site\HomeStory;
use Tests\TestCase;

/**
 * ANA SAYFANIN SAHNESİ ve İDDİALARI — `docs/138`.
 *
 * `PublicHomeContractTest` sayfanın SUNUCUDA üretildiğini, akışkan
 * olduğunu ve React yüklemediğini donduruyor. Bu dosya onun üstüne dört
 * yeni soru ekliyor ve dördü de sahibin bu paketteki açık isteklerinden
 * doğdu:
 *
 *   1. Dar ekran gerçekten TABAN mı — yoksa masaüstü için yazılıp geri mi
 *      alınıyor? (HOME-SCENE-01)
 *   2. Hareket açıkça istenmeden doğuyor mu? (HOME-SCENE-02)
 *   3. Süsleme gezinmeyi ya da hukuki bir seçimi örtebiliyor mu?
 *      (HOME-SCENE-03/04)
 *   4. Sayfadaki iddialar ürünün kendi envanteriyle aynı mı, yoksa
 *      uydurulabilir mi? (HOME-REAL-07)
 *
 * Requirement ID'leri: HOME-SCENE-01…05, HOME-REAL-07, HOME-HONEST-08.
 */
final class HomeSceneContractTest extends TestCase
{
    private const MOTION_CSS = 'resources/css/site-motion.css';

    private const HOME_VIEW = 'resources/views/public/home.blade.php';

    private function css(): string
    {
        return (string) file_get_contents(base_path(self::MOTION_CSS));
    }

    private function template(): string
    {
        return (string) file_get_contents(base_path(self::HOME_VIEW));
    }

    private function html(): string
    {
        return (string) $this->get('/')->assertOk()->getContent();
    }

    // --- HOME-SCENE-01 : dar ekran TABAN, yazım sırası da ------------------

    /**
     * Sahibin cümlesi (2026-09-08): *"Sadece media query değil, gerçek
     * mobile first."*
     *
     * Ölçülebilir karşılığı budur: masaüstü için yazılıp dar ekranda geri
     * alınan bir kural YOKTUR. `max-width` bir medya sorgusu, `max-*:` bir
     * Tailwind varyantı olarak tam bunu yapar — geniş ekranın kuralını
     * taban sayıp dar ekranda bastırır. Çıktı benzese bile borç oradan
     * birikir: ikinci düzen indirilir, odaklanılabilir kalır, bakım ister.
     */
    public function test_no_rule_is_written_for_a_wide_screen_and_undone_on_a_narrow_one(): void
    {
        $css = $this->css();

        self::assertDoesNotMatchRegularExpression(
            '/@media[^{]*\bmax-width\b/i',
            $css,
            'HOME-SCENE-01: `max-width` medya sorgusu — geniş ekran kuralı taban sayılıp '
            .'dar ekranda bastırılıyor. Taban 320 pikseldir; geniş ekran onun ÜSTÜNE eklenir.'
        );

        self::assertDoesNotMatchRegularExpression(
            '/@media[^{]*\bmin-width\b/i',
            $css,
            'HOME-SCENE-01: genişlik kırılma noktası — düzen `clamp()`, `min()` ve '
            .'`repeat(auto-fit, minmax(…))` ile akışkan yazılır (`MP-05`).'
        );
    }

    public function test_the_home_page_hides_nothing_on_a_narrow_screen(): void
    {
        preg_match_all('/class="([^"]*)"/', $this->template(), $matches);

        foreach ($matches[1] as $classList) {
            self::assertDoesNotMatchRegularExpression(
                '/(^|\s)max-(sm|md|lg|xl|2xl):/',
                $classList,
                'HOME-SCENE-01: `max-*` bastırması bulundu: '.$classList
            );

            self::assertDoesNotMatchRegularExpression(
                '/(^|\s)hidden(\s|$)/',
                $classList,
                'HOME-SCENE-01: "dar ekranda gizle" bulundu: '.$classList
                .' — gizlenen şey yine indirilir, yine odaklanılabilir, yine bakım ister.'
            );
        }
    }

    /**
     * TABAN 320×**480** (iPhone 4). Yükseklik de tabandır.
     *
     * Görüntü alanını dolduran bir "hero" sahnesi, 480 piksellik bir
     * ekranda içeriği katlanmanın altına iter — ne kadar güzel olursa
     * olsun o sahne orada bir kusurdur. Bu yüzden sahne yüksekliğini
     * görüntü alanından DEĞİL içeriğinden alır.
     */
    public function test_no_scene_claims_the_whole_viewport_height(): void
    {
        self::assertDoesNotMatchRegularExpression(
            '/(min-)?(block-size|height)\s*:\s*[^;]*\b\d+(vh|svh|dvh|lvh)\b/i',
            $this->css(),
            'HOME-SCENE-01: görüntü alanı yüksekliğinde bir sahne — 480 pikselde içeriği '
            .'katlanmanın altına iter. Sahnenin boyu içeriğinden gelir.'
        );
    }

    // --- HOME-SCENE-02 : hareket açıkça istenmeden doğmaz -----------------

    /**
     * Kural `no-preference` üzerinden yazılır, `reduce` üzerinden DEĞİL:
     * ikinci biçimde animasyon önce tanımlanır sonra iptal edilir ve
     * iptali yazmayı unutan bir satır sessizce hareket eder
     * (`docs/136` §7.3).
     *
     * Ölçüm kaba değil KESİN: dosyadaki `@media (prefers-reduced-motion:
     * no-preference)` bloğunun sınırları sayılarak bulunur ve o bloğun
     * DIŞINDA kalan her `animation`/`transition` bildirimi ihlaldir.
     */
    public function test_every_motion_declaration_sits_behind_the_reduced_motion_gate(): void
    {
        $css = $this->css();
        $gate = $this->reducedMotionBlock($css);

        $offenders = [];

        preg_match_all(
            '/(?<![\w-])(animation|animation-name|transition|transition-property)\s*:/',
            $css,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        foreach ($matches[0] as [$declaration, $offset]) {
            if ($offset < $gate['start'] || $offset > $gate['end']) {
                $offenders[] = $declaration.' @ '.$offset;
            }
        }

        self::assertSame(
            [],
            $offenders,
            'HOME-SCENE-02: hareket bildirimi `prefers-reduced-motion: no-preference` '
            .'kapısının DIŞINDA: '.implode(', ', $offenders)
            .' — hareket, açıkça istenmiş olmadan hiç doğmamalı.'
        );
    }

    public function test_the_motion_hook_is_css_not_script(): void
    {
        $gate = $this->reducedMotionBlock($this->css());
        $body = substr($this->css(), $gate['start'], $gate['end'] - $gate['start']);

        /*
            İKİNCİ KAPI. `data-motion="on"` özniteliğini betik yazar; ama
            kural CSS'te bir kez daha kısıtlanır. Böylece `reduce` diyen bir
            ziyaretçide betik özniteliği yazsa BİLE hiçbir sahne kuralı
            doğmaz — tek yönlü bir kapı, ve betik onu geri alamaz.
        */
        preg_match_all('/^\s{4}(?![\s}@\/*])([^{}\n]+)\{/m', $body, $selectors);

        foreach ($selectors[1] as $selector) {
            self::assertStringContainsString(
                "data-motion='on'",
                $selector,
                'HOME-SCENE-02: kapının içindeki bir kural `data-motion` kancasını taşımıyor: '
                .trim($selector).' — hareket iki kapının ARDINDA olmalı.'
            );
        }
    }

    public function test_the_page_carries_exactly_one_dominant_animated_background(): void
    {
        /*
            `docs/118` E5 madde 6: sayfa başına EN FAZLA bir baskın hareketli
            arka plan. Hareketin etkisi nadirliğinden gelir; her bölümde
            kıpırdayan bir arka plan üçüncü bölümde gürültüdür.
        */
        self::assertSame(
            1,
            substr_count($this->template(), 'class="site-stage site-scene"'),
            'HOME-SCENE-02: sayfada birden fazla baskın sahne var (`docs/118` E5 md. 6).'
        );
    }

    // --- HOME-SCENE-03/04 : süsleme hiçbir şeyi örtemez -------------------

    public function test_no_scene_layer_rises_above_the_content(): void
    {
        preg_match_all('/z-index\s*:\s*([^;]+);/', $this->css(), $matches);

        foreach ($matches[1] as $value) {
            self::assertMatchesRegularExpression(
                '/var\(--layer-scene-(back|mid|front)\)/',
                trim($value),
                'HOME-SCENE-03: sahne katmanı `--layer-scene-*` dışında bir yığın kullanıyor: '
                .trim($value).' — hiçbir dekoratif katman gezinmeyi (20), çerez şeridini (30) '
                .'ya da atlama bağlantısını (50) örtemez.'
            );
        }
    }

    public function test_every_decorative_layer_is_invisible_to_a_screen_reader(): void
    {
        preg_match_all('/<div class="site-stage-layer[^>]*>/', $this->template(), $matches);

        self::assertNotSame([], $matches[0], 'Sahne katmanı bulunamadı — ölçüm dayanaksız.');

        foreach ($matches[0] as $layer) {
            self::assertStringContainsString(
                'aria-hidden="true"',
                $layer,
                'HOME-SCENE-04: dekoratif katman ekran okuyucuya okunuyor: '.$layer
                .' — boş bir kutu, sesli okunduğunda bir engeldir.'
            );
        }
    }

    // --- HOME-SCENE-05 : betiksiz sayfa EKSİKSİZ --------------------------

    /**
     * `docs/118` E8: taban HTML, tavan serbest. Betik hareketin ÜSTÜNE
     * eklenir; hiçbir metni ve hiçbir hedefi o taşımaz.
     *
     * Ölçüm gerçekçidir: gövde, betik etiketleri atılmış hâlde taranır —
     * arama motoru ve JavaScript çalıştırmayan bir bot için varsayılan
     * gövde budur.
     */
    public function test_the_page_is_complete_without_any_script(): void
    {
        $withoutScripts = (string) preg_replace('#<script\b[^>]*>.*?</script>#is', '', $this->html());
        $withoutScripts = (string) preg_replace('#<script\b[^>]*/?>#is', '', $withoutScripts);

        $strings = app(SiteText::class)->all('en');

        foreach ([
            $strings['homeHeroHeading'],
            $strings['homeChainHeading'],
            $strings['homePartsHeading'],
            $strings['homeLimitsHeading'],
        ] as $heading) {
            self::assertStringContainsString(
                e($heading),
                $withoutScripts,
                "HOME-SCENE-05: betiksiz gövdede \"{$heading}\" yok."
            );
        }

        foreach (['/register', '/app', '/pricing', '/contact'] as $target) {
            self::assertStringContainsString(
                'href="'.$target.'"',
                $withoutScripts,
                "HOME-SCENE-05: betiksiz gövdede {$target} hedefi yok."
            );
        }

        /*
            SORU-CEVAP KAPALIYKEN DE OKUNUR. `<details>` içeriği HTML'de
            durur; bir arama motoru onu görür. Betiğe bağlı bir açılır
            bölme, kapalıyken HİÇ var olmazdı.
        */
        self::assertStringContainsString(
            e($strings['homeFaqPosAnswer']),
            $withoutScripts,
            'HOME-SCENE-05: kapalı bir soru-cevap bölmesinin cevabı betiksiz gövdede yok.'
        );
    }

    // --- HOME-REAL-07 : iddia uydurulmaz, envantere bağlı ----------------

    /**
     * Ana sayfanın yetenek listesi, ürünün kendi genel bakış sayfasının
     * (`/urun/`) yetenek envanteriyle **aynı sırada ve aynı terimlerle**
     * durur.
     *
     * Bu, "sahte özellik listesi" sorununun kod düzeyindeki cevabı: bir
     * yetenek üründen düşerse ya da adı değişirse, pazarlama sayfası eski
     * iddiayı SESSİZCE taşımaya devam edemez. Bir pazarlama sayfasında bir
     * satır silmeyi kimse hatırlamaz; kırmızı bir test hatırlatır.
     */
    public function test_every_capability_named_on_the_home_page_exists_in_the_product_inventory(): void
    {
        $this->assertMirrors(HomeStory::PARTS, BlockType::Capabilities, 'yetenek');
    }

    public function test_every_limit_named_on_the_home_page_exists_in_the_product_inventory(): void
    {
        $this->assertMirrors(HomeStory::LIMITS, BlockType::Limitations, 'sınır');
    }

    public function test_the_chain_on_the_home_page_is_the_products_own_order(): void
    {
        $this->assertMirrors(HomeStory::CHAIN, BlockType::HowItWorks, 'adım');
    }

    /**
     * Her yetenek iddiasının arkasında ONU ÜRETEN bir dosya var.
     *
     * Envanterdeki `source` alanı bir yorum değil bir adres: dosya
     * silinirse iddia dayanaksız kalır ve bu test onu bulur.
     */
    public function test_every_claim_points_at_a_file_that_exists(): void
    {
        $missing = [];

        foreach ([BlockType::Capabilities, BlockType::HowItWorks, BlockType::Limitations] as $type) {
            foreach ($this->block($type)->entries as $entry) {
                if ($entry->source !== null && ! file_exists(base_path($entry->source))) {
                    $missing[] = $entry->source;
                }
            }
        }

        self::assertSame(
            [],
            $missing,
            'HOME-REAL-07: iddianın dayandığı dosya yok: '.implode(', ', $missing)
        );
    }

    // --- HOME-HONEST-08 : uydurma kanıt yok ------------------------------

    public function test_the_page_invents_no_proof_it_does_not_have(): void
    {
        $html = strtolower($this->html());

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
        ] as $claim) {
            self::assertStringNotContainsString(
                $claim,
                $html,
                "HOME-HONEST-08: \"{$claim}\" — yatırımcıya hazırlık uydurmayla değil, "
                .'yapılmış işi göstererek kurulur.'
            );
        }

        /*
            "1000+ restoran" ailesi. Ürünün bugün kaç müşterisi olduğu bu
            depoda ÖLÇÜLEMEZ; ölçülemeyen bir sayıyı sayfaya yazmak, ilk
            soruda çöken bir iddiadır.

            Fiyat ve sürüm numaraları muaf değil ÇÜNKÜ desen artı işareti
            arıyor: "1000+" kırar, "24,90 TRY" kırmaz.
        */
        self::assertDoesNotMatchRegularExpression(
            '/\b\d[\d.,]*\s*\+\s*(restaurant|customer|business|user|venue|table)/i',
            $html,
            'HOME-HONEST-08: sayılmamış bir müşteri sayısı ilan ediliyor.'
        );
    }

    // --- yardımcılar -----------------------------------------------------

    /**
     * @param  list<string>  $stems
     */
    private function assertMirrors(array $stems, BlockType $type, string $noun): void
    {
        $text = app(SiteText::class);
        $inventory = array_values(array_map(
            static fn ($entry): ?string => $entry->term,
            $this->block($type)->entries,
        ));

        $onPage = array_map(
            static fn (string $stem): string => $text->get($stem.'.title', 'en'),
            $stems,
        );

        self::assertSame(
            $inventory,
            $onPage,
            "HOME-REAL-07: ana sayfadaki {$noun} listesi ürünün kendi envanteriyle "
            .'(`ProductOverviewPage`) ayrışmış. Ürüne bir parça eklendiğinde ya da bir '
            .'parça düştüğünde bu liste onunla birlikte döner; ikinci bir gerçek kaynak '
            .'doğamaz.'
        );
    }

    private function block(BlockType $type): ContentBlock
    {
        foreach (ProductOverviewPage::content()->blocks as $block) {
            if ($block->type === $type) {
                return $block;
            }
        }

        self::fail("`ProductOverviewPage` içinde {$type->value} bloğu yok — ölçüm dayanaksız.");
    }

    /** @return array{start: int, end: int} */
    private function reducedMotionBlock(string $css): array
    {
        /* Kapı SATIR BAŞINDA aranır. Düz bir `strpos`, dosyanın kendi
           başlık yorumundaki örnek satırı bulur ve blok sınırlarını orada
           sayardı — kapı o zaman hiçbir şey ölçmezdi. */
        $found = preg_match(
            '/^@media \(prefers-reduced-motion: no-preference\) \{/m',
            $css,
            $match,
            PREG_OFFSET_CAPTURE
        );

        self::assertSame(
            1,
            $found,
            'HOME-SCENE-02: `prefers-reduced-motion: no-preference` kapısı hiç yok.'
        );

        $start = (int) $match[0][1];

        /* Süslü parantezler sayılarak blok sonu bulunur; kaba bir
           `strrpos('}')` dosyanın sonunu verirdi ve kapı hiçbir şey
           ölçmezdi. */
        $depth = 0;
        $offset = (int) strpos($css, '{', $start);

        for ($i = $offset, $len = strlen($css); $i < $len; $i++) {
            if ($css[$i] === '{') {
                $depth++;
            } elseif ($css[$i] === '}') {
                $depth--;

                if ($depth === 0) {
                    return ['start' => $start, 'end' => $i];
                }
            }
        }

        self::fail('HOME-SCENE-02: hareket kapısının kapanışı bulunamadı.');
    }
}
