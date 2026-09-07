<?php

declare(strict_types=1);

namespace Tests\Feature\QrDestination;

use App\Application\Publication\UseCase\ApplyGuestRichMedia;
use App\Domain\Entitlement\Entitlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\GrantsPlanEntitlements;
use Tests\TestCase;

/**
 * FF-212 — ZENGİN GÖRSEL MİSAFİR YÜZEYİ VE PLAN KADEMESİ
 * (`docs/114` §3 Dalga 6, `docs/122` Y6).
 *
 * ═══ PAKETİN SEBEBİ: SATILAN BİR ŞEYİN ÜRÜNÜ YOKTU ═══
 *
 * `menu.rich-media` hakkı tanımlıydı, `restaurant` kademesine bağlıydı ve
 * satılabiliyordu — ama misafir tarafında bir karşılığı yoktu: parası alınsa
 * bile masadaki misafirin gördüğü sayfa değişmiyordu. Fiyat sayfası bu yüzden
 * hakkı bilerek SUSUYORDU (`PricingPage::WITHHELD`). Bu paket yüzeyi açar ve
 * o susmayı sona erdirir.
 *
 * ═══ ÜÇ KEMİK KARAR ═══
 *
 * 1. HAK YOKSA FOTOĞRAF YOK, VE BU DÜRÜSTÇE OLUR. Boş çerçeve yok, kırık
 *    görsel yer tutucusu yok, "yükseltin" reklamı yok. Misafir müşteri
 *    değildir: masadaki insan restoranın misafiridir, bizim satış hedefimiz
 *    değil. Menü, fiyat ve alerjen HER kademede görünür — kademe bir yeteneği
 *    açar, temel yolculuğu kapatmaz (`Entitlement`).
 *
 * 2. KARAR DONMUŞ HAKTAN OKUNUR, CANLI PLANDAN DEĞİL. Sahip planını
 *    düşürdüğünde masadaki basılı karekod aynı kâğıttır ve o kâğıdın
 *    gösterdiği yayın değişmez; fark BİR SONRAKİ yayında görünür. Bu depoda
 *    tam bu kusur bir kez yaşandı: `EloquentPublicationRepository::current()`
 *    donmuş hakkı taşımayı atlamıştı ve her istek sessizce canlı plana
 *    düşüyordu. Kusur görünmezdi çünkü canlı plan çoğu zaman aynı cevabı
 *    verir; yalnız plan DEĞİŞTİĞİNDE ayrışır. Aşağıda iki yön de dondurulmuş.
 *
 * 3. KALDIRMA VERİDE YAPILIR, İŞARETLEMEDE DEĞİL. `image` bloğu anlık
 *    görüntüden çıkarılır; şablon hiç fotoğraf olmamış gibi çizer. Şablonda
 *    gizlemek, yapılandırılmış veride fotoğrafı yine bildirmek ve ürün
 *    sayfasının kalite kapısını yanıltmak olurdu.
 *
 * Requirement ID'leri: GUEST-RICH-MEDIA-GRANTED-01, GUEST-RICH-MEDIA-DENIED-02,
 * GUEST-RICH-MEDIA-FROZEN-03, GUEST-RICH-MEDIA-LEGACY-04,
 * GUEST-RICH-MEDIA-ITEM-05, GUEST-RICH-MEDIA-NO-EXTERNAL-06,
 * GUEST-RICH-MEDIA-NO-JUMP-07, GUEST-RICH-MEDIA-TOUCH-08,
 * GUEST-RICH-MEDIA-320-09, GUEST-RICH-MEDIA-PREVIEW-10.
 */
final class GuestRichMediaTest extends TestCase
{
    use GrantsPlanEntitlements;
    use RefreshDatabase;

    /** `docs/48` §1 — 320 px (iPhone 4) gerçek başlangıç noktasıdır. */
    private const VIEWPORT_320 = 320;

    /** Kaynak `--tap` ve depo `--aep-hit-area` aynı değerde buluşur. */
    private const MIN_TAP_PX = 44;

    /**
     * Fotoğraflı satırın ekranda kapladığı yer — 320 px'te ölçülmüş bütçe.
     *
     * Görsel dar ekranda EN KIT KAYNAĞI (ekran alanı) tüketir ve doğru
     * bileşim "büyük dokunma hedefi + SIKI boşluk"tur. Kartın görsel şeridi
     * 96 px'tir ve kart dolgusu 10/12 px'te kalır: 320'de ürün adına ~180 px
     * kalır ve ad hiçbir genişlikte sıfıra inmez.
     */
    private const IMAGE_STRIP_PX = 96;

    // --- Sahne --------------------------------------------------------------

    /**
     * Fotoğraf bloğu — `MenuMediaPort::snapshotImage()` ne üretiyorsa o şekil.
     *
     * Yol GÖRELİDİR ve öyle kalmalı: misafir menüsü bu üründe SIFIR dış istek
     * ilkesiyle ölçülüyor (`GuestMenuFindTest`). Bir CDN, bir dış görsel
     * barındırıcı ya da bir izleme pikseli buraya girerse 06 kırılır.
     *
     * @return array<string, mixed>
     */
    private function imageBlock(): array
    {
        return [
            'sources' => [
                ['url' => '/media/deliver/kahve-320.webp', 'width' => 320],
                ['url' => '/media/deliver/kahve-640.webp', 'width' => 640],
            ],
            'width' => 640,
            'height' => 640,
            'altText' => 'Fincanda sıcak kahve',
            'lqip' => 'data:image/webp;base64,UklGRhIAAABXRUJQ',
        ];
    }

    // --- GUEST-RICH-MEDIA-GRANTED-01 ---------------------------------------

    public function test_a_tenant_with_the_right_shows_the_photograph_to_the_guest(): void
    {
        $scene = $this->scene('gorsel-hakli', [Entitlement::MenuRichMedia]);

        $html = $this->guestMenu($scene['token']);

        self::assertTrue(
            $this->drawsPhotograph($html),
            'GUEST-RICH-MEDIA-GRANTED-01: hakkı olan kiracıda fotoğraf çizilmeli.'
        );

        self::assertStringContainsString(
            '/media/deliver/kahve-320.webp',
            $html,
            'GUEST-RICH-MEDIA-GRANTED-01: fotoğrafın kendi adresi basılmalı.'
        );

        self::assertStringContainsString(
            'Fincanda sıcak kahve',
            $html,
            'GUEST-RICH-MEDIA-GRANTED-01: alternatif metin ekran okuyucunun tek kaynağıdır.'
        );
    }

    // --- GUEST-RICH-MEDIA-DENIED-02 ----------------------------------------

    public function test_a_tenant_without_the_right_shows_no_photograph_and_no_apology(): void
    {
        /*
            HAKKI OLMAYAN KİRACIDA MİSAFİR NE GÖRÜR?

            Menüyü görür: ürün adı, fiyat, alerjen, "bugün tükendi" — hepsi
            yerinde. Görmediği tek şey fotoğraftır ve bunu FARK ETMEZ:
            fotoğrafın yerinde boş bir çerçeve, gri bir kutu, kırık bir görsel
            simgesi ya da "bu restoran fotoğraf paketini almadı" cümlesi YOK.

            Ve ona plan SATILMAZ. "Daha fazlası için yükseltin" yazmak,
            restoranın masasını bizim satış kanalımıza çevirmek olurdu; eksiği
            gören taraf sahiptir ve o fiyat sayfasında okur.
        */
        $scene = $this->scene('gorsel-haksiz', [Entitlement::BrandingCustom]);

        $html = $this->guestMenu($scene['token']);

        // Menü GİZLENMEZ: kapanan şey süstür, temel yolculuk değil.
        self::assertStringContainsString('Kahve', $html, 'GUEST-RICH-MEDIA-DENIED-02: menü yine de görünmeli.');
        self::assertStringContainsString('Sıcak İçecek', $html, 'GUEST-RICH-MEDIA-DENIED-02: kategori yine de görünmeli.');

        self::assertFalse(
            $this->drawsPhotograph($html),
            'GUEST-RICH-MEDIA-DENIED-02: hakkı olmayan kiracıda fotoğraf çizilmez.'
        );

        foreach (['/media/deliver/', 'Fincanda sıcak kahve', 'data:image/webp'] as $trace) {
            self::assertStringNotContainsString(
                $trace,
                $html,
                "GUEST-RICH-MEDIA-DENIED-02: `{$trace}` sayfada kalamaz; fotoğraf VERİDEN çıkarılır."
            );
        }

        // Boş çerçeve ya da yer tutucu: hiçbir `<img>` kalmamalı — sayfanın
        // kendi logosu bu sahnede yok, dolayısıyla sayım nettir.
        self::assertSame(
            0,
            preg_match_all('/<img\b/i', $html),
            'GUEST-RICH-MEDIA-DENIED-02: boş çerçeve de bir yer tutucudur; hiç çizilmez.'
        );

        // MİSAFİRE PLAN SATILMAZ.
        foreach (['upgrade', 'yükselt', 'plan', 'pricing', 'fiyatlandirma', 'abonelik'] as $pitch) {
            self::assertStringNotContainsString(
                $pitch,
                mb_strtolower($html),
                "GUEST-RICH-MEDIA-DENIED-02: `{$pitch}` misafire söylenmez; o restoranın misafiri, bizim müşterimiz değil."
            );
        }
    }

    // --- GUEST-RICH-MEDIA-FROZEN-03 ----------------------------------------

    public function test_the_decision_is_read_from_the_frozen_rights_not_the_live_plan(): void
    {
        /*
            İKİ YÖN, TEK KURAL — VE İKİSİ DE ÖLÇÜLÜR.

            Tek yönü ölçmek yetmez: canlı plan çoğu zaman donmuş hakla aynı
            cevabı verir, dolayısıyla yalnız "hak varken görünüyor" demek
            kusuru yakalamaz. Ayrışma ancak plan DEĞİŞTİĞİNDE görünür.

            1. Yayın hakla donmuş, plan sonradan DÜŞMÜŞ → fotoğraf DURUR.
               Masadaki basılı karekod aynı kâğıttır; ödeme gecikmesi masada
               oturan misafirin ekranını ortasından kesmez.

            2. Yayın haksız donmuş, plan sonradan YÜKSELMİŞ → fotoğraf YOK.
               Aynı kural ters yönde de geçerli olmasaydı "donmuş" kelimesi
               yalnız işine geldiğinde doğru olurdu; fark bir sonraki yayında
               görünür.
        */
        $downgraded = $this->scene('gorsel-donmus-dusen', [Entitlement::MenuRichMedia]);
        $this->setLivePlan($downgraded['workspaceId'], [Entitlement::BrandingCustom]);

        self::assertTrue(
            $this->drawsPhotograph($this->guestMenu($downgraded['token'])),
            'GUEST-RICH-MEDIA-FROZEN-03: yayına donmuş hak, planın düşmesinden etkilenmez.'
        );

        $upgraded = $this->scene('gorsel-donmus-yukselen', [Entitlement::BrandingCustom]);
        $this->setLivePlan($upgraded['workspaceId'], [Entitlement::MenuRichMedia]);

        self::assertFalse(
            $this->drawsPhotograph($this->guestMenu($upgraded['token'])),
            'GUEST-RICH-MEDIA-FROZEN-03: yükselen plan ESKİ yayına fotoğraf koymaz; fark bir sonraki yayında görünür.'
        );
    }

    // --- GUEST-RICH-MEDIA-LEGACY-04 ----------------------------------------

    public function test_a_publication_made_before_the_field_existed_falls_back_to_the_live_plan(): void
    {
        /*
            BOŞ LİSTE İLE `null` AYNI ŞEY DEĞİLDİR.

            Boş liste "o gün hiçbir hak yoktu" der; `null` ise "o gün ne olduğu
            bilinmiyor" der. İkincisini boş liste saymak, alan eklenmeden önce
            yayınlanmış her menüyü geriye dönük olarak haksız ilan etmek olurdu
            — yani ödemesini yapmış restoranların fotoğrafları bir göç
            sabahında sessizce kaybolurdu.
        */
        $scene = $this->scene('gorsel-eski-yayin', [Entitlement::MenuRichMedia]);
        DB::table('menu_publications')->where('workspace_id', $scene['workspaceId'])->update(['entitlements' => null]);

        self::assertTrue(
            $this->drawsPhotograph($this->guestMenu($scene['token'])),
            'GUEST-RICH-MEDIA-LEGACY-04: hakkı bilinmeyen yayın canlı plana düşer ve plan hakkı veriyor.'
        );

        $this->setLivePlan($scene['workspaceId'], [Entitlement::BrandingCustom]);

        self::assertFalse(
            $this->drawsPhotograph($this->guestMenu($scene['token'])),
            'GUEST-RICH-MEDIA-LEGACY-04: hakkı bilinmeyen yayında canlı plan neyi diyorsa o olur.'
        );
    }

    // --- GUEST-RICH-MEDIA-ITEM-05 ------------------------------------------

    public function test_the_dish_page_and_the_link_to_it_obey_the_same_decision(): void
    {
        /*
            KALDIRMA VERİDE YAPILDIĞI İÇİN KALİTE KAPISI DA KENDİLİĞİNDEN
            DOĞRU OLUR.

            Ürün sayfası ancak "anlatacak şeyi" olan ürün için kurulur
            (`ShowPublicMenuItemController::hasSomethingToSay`) ve bu sahnedeki
            ürünün tek fazlası fotoğraftır. Hak yoksa fotoğraf artık veride de
            yoktur; dolayısıyla menüde o ürüne bağlantı ÇİZİLMEZ ve sayfası
            arama motoruna kapanır. Şablonda gizleseydik, misafir bir bağlantıya
            basar ve karşısında menüdeki satırın kopyasını bulurdu.
        */
        $granted = $this->scene('gorsel-urun-hakli', [Entitlement::MenuRichMedia]);
        $grantedHtml = $this->guestMenu($granted['token']);
        $grantedPath = $this->itemPath($grantedHtml, $granted['menuItemId']);

        self::assertStringContainsString(
            $grantedPath,
            $grantedHtml,
            'GUEST-RICH-MEDIA-ITEM-05: anlatacak şeyi olan ürün menüde bağlantı olmalı.'
        );

        $grantedPage = $this->withHeaders(['Accept' => 'text/html'])->get($grantedPath);
        $grantedPage->assertOk();
        self::assertTrue(
            $this->drawsPhotograph((string) $grantedPage->getContent()),
            'GUEST-RICH-MEDIA-ITEM-05: hakkı olan kiracıda ürün sayfası fotoğrafı gösterir.'
        );
        self::assertNull(
            $grantedPage->headers->get('X-Robots-Tag'),
            'GUEST-RICH-MEDIA-ITEM-05: anlatacak şeyi olan sayfa indekslenebilir kalmalı.'
        );

        $denied = $this->scene('gorsel-urun-haksiz', [Entitlement::BrandingCustom]);
        $deniedHtml = $this->guestMenu($denied['token']);
        $deniedPath = $this->itemPath($deniedHtml, $denied['menuItemId']);

        self::assertStringNotContainsString(
            $deniedPath,
            $deniedHtml,
            'GUEST-RICH-MEDIA-ITEM-05: hiçbir yere götürmeyen bir bağlantı kurulmaz.'
        );

        $deniedPage = $this->withHeaders(['Accept' => 'text/html'])->get($deniedPath);
        $deniedPage->assertOk();
        self::assertFalse(
            $this->drawsPhotograph((string) $deniedPage->getContent()),
            'GUEST-RICH-MEDIA-ITEM-05: ürün sayfası da aynı kararı uygular.'
        );
        self::assertSame(
            'noindex, follow',
            $deniedPage->headers->get('X-Robots-Tag'),
            'GUEST-RICH-MEDIA-ITEM-05: anlatacak şeyi kalmayan sayfa arama motoruna açılmaz.'
        );
    }

    // --- GUEST-RICH-MEDIA-NO-EXTERNAL-06 -----------------------------------

    public function test_the_photograph_costs_the_guest_zero_external_requests(): void
    {
        /*
            SIFIR DIŞ İSTEK — BU ÜRÜNÜN MİSAFİR MENÜSÜNDEKİ ÖLÇÜSÜ
            (`GuestMenuFindTest` deseni).

            Fotoğraf bir CDN'den, bir dış görsel barındırıcıdan ya da bir
            izleme pikselinden gelmez: aynı sunucudan, göreli bir yoldan
            gelir. Masadaki telefon zayıf bir hücresel hatta; ikinci bir alan
            adı, ikinci bir DNS çözümü ve ikinci bir TLS el sıkışması demektir
            ve bunun bedelini restoran değil misafir öder.
        */
        $html = $this->guestMenu($this->scene('gorsel-dis-istek', [Entitlement::MenuRichMedia])['token']);

        /*
            KANONİK ADRES BİR İSTEK DEĞİLDİR. `<link rel="canonical">` ve
            `og:url` mutlak olmak ZORUNDA (arama motoru için) ama tarayıcı
            onları indirmez. Ölçülen şey sayfanın KENDİLİĞİNDEN indirdiği
            kaynaklardır: `src`, `srcset` ve kaynak indiren `<link>` türleri.
        */
        preg_match_all('#\b(?:src|srcset)\s*=\s*"([^"]+)"#i', $html, $matches);

        foreach ($matches[1] as $value) {
            foreach (preg_split('/\s*,\s*/', $value) ?: [] as $candidate) {
                $url = trim(preg_split('/\s+/', trim($candidate))[0] ?? '');

                self::assertDoesNotMatchRegularExpression(
                    '#^(?:https?:)?//#i',
                    $url,
                    "GUEST-RICH-MEDIA-NO-EXTERNAL-06: `{$url}` sayfaya ikinci bir alan adı sokuyor."
                );
            }
        }

        preg_match_all('#<link[^>]*rel="([^"]+)"[^>]*>#i', $html, $links, PREG_SET_ORDER);

        foreach ($links as $link) {
            if (! preg_match('/\b(?:stylesheet|preload|prefetch|preconnect|dns-prefetch|modulepreload)\b/i', $link[1])) {
                continue;
            }

            self::assertDoesNotMatchRegularExpression(
                '#href="(?:https?:)?//#i',
                $link[0],
                'GUEST-RICH-MEDIA-NO-EXTERNAL-06: kaynak indiren bir `<link>` dışarıya bakamaz.'
            );
        }
    }

    // --- GUEST-RICH-MEDIA-NO-JUMP-07 ---------------------------------------

    public function test_the_layout_does_not_jump_while_the_photograph_loads(): void
    {
        /*
            YÜKLENMEMİŞ BİR GÖRSEL DE YER KAPLAR.

            Boyutsuz bir `<img>` yüklenene kadar sıfır yükseklikteki bir
            kutudur ve indiği anda altındaki her şeyi aşağı iter. Masadaki
            misafir tam okumaya başladığı satırı kaybeder — ve en kötü hâlde
            basmak istediği yere başka bir şey gelir.

            Üç önlem birlikte çalışır: `width`/`height` öznitelikleri oranı
            baştan söyler, `aspect-ratio` kutuyu CSS tarafında sabitler,
            `lqip` ise beklerken boş bir gri yerine bulanık bir önizleme
            gösterir. `loading="lazy"`: kırk ürünlük bir menüde misafir ilk
            ekranı görmek için kırk fotoğraf beklemez.
        */
        $html = $this->guestMenu($this->scene('gorsel-ziplama', [Entitlement::MenuRichMedia])['token']);

        preg_match('#<img[^>]*class="qr-menu-item-image"[^>]*>#i', $html, $tag);
        self::assertNotEmpty($tag, 'GUEST-RICH-MEDIA-NO-JUMP-07: fotoğraf çizilmemiş.');

        foreach (['width="640"', 'height="640"', 'loading="lazy"', 'decoding="async"'] as $needed) {
            self::assertStringContainsString(
                $needed,
                $tag[0],
                "GUEST-RICH-MEDIA-NO-JUMP-07: `{$needed}` olmadan düzen zıplar ya da ilk ekran gecikir."
            );
        }

        self::assertStringContainsString(
            'data:image/webp;base64',
            $tag[0],
            'GUEST-RICH-MEDIA-NO-JUMP-07: beklerken boş gri bir kutu yerine bulanık önizleme durur.'
        );

        self::assertMatchesRegularExpression(
            '/\.qr-menu-item-image\s*\{[^}]*aspect-ratio\s*:\s*1/s',
            $this->styleBlocks($html),
            'GUEST-RICH-MEDIA-NO-JUMP-07: kutunun oranı CSS tarafında da sabit olmalı.'
        );
    }

    // --- GUEST-RICH-MEDIA-TOUCH-08 -----------------------------------------

    public function test_nothing_about_the_photograph_is_told_only_by_hover(): void
    {
        /*
            DOKUNMA İLE İŞARETLEYİCİ AYRI ETKİLEŞİM MODELİDİR.

            Dokunmalı bir cihazda `hover` YOKTUR: yalnız imleçle anlatılan
            hiçbir bilgi masadaki telefonda var olmaz. Bu yüzey bugün hiçbir
            `:hover` kuralı taşımıyor ve fotoğrafın etrafında da taşımamalı —
            "üstüne gelince büyür" gibi bir davranış, misafirin çoğunluğu için
            hiç var olmayan bir yetenektir.

            Fotoğrafı BÜYÜTME yolu bir katman ya da bir betik değil, ürünün
            kendi sayfasıdır: ürün adı bir bağlantıdır (05) ve o sayfada
            fotoğraf tam genişlikte durur. Bağlantı dokunmada da imleçte de
            aynı şekilde çalışır ve tek bayt JavaScript istemez.

            `title` özniteliği de aynı kapıya çıkar: dokunmada hiç görünmez.
        */
        $html = $this->guestMenu($this->scene('gorsel-dokunma', [Entitlement::MenuRichMedia])['token']);

        /*
            YORUMLAR ÖNCE DÜŞER. Bu deponun stil blokları gerekçelerini kodun
            içine yazıyor ve o gerekçelerin biri kelimesi kelimesine
            "hover değil `:active`" diyor. Yorumu kural sanmak, doğru yazılmış
            bir kararı ihlal olarak bildirmek olurdu.
        */
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $this->styleBlocks($html));

        /*
            SAYFADA TEK BİR `:hover` VAR ve o da `@media (hover: hover) and
            (pointer: fine)` içinde bir arka plan tonu — yani gerçekten faresi
            olan cihazda bir SÜS. Kural "hover kullanma" değil, "hover ile
            BİLGİ verme"dir: dokunmalı cihazda o kural hiç uygulanmaz ve
            uygulanmadığında kaybolan bir şey olmamalı.
        */
        preg_match_all('/(?<selector>[^{}]*:hover[^{}]*)\{(?<body>[^}]*)\}/i', $css, $hovers, PREG_SET_ORDER);

        foreach ($hovers as $rule) {
            self::assertStringNotContainsString(
                'image',
                $rule['selector'],
                'GUEST-RICH-MEDIA-TOUCH-08: fotoğrafın davranışı imlece bağlanamaz.'
            );

            foreach (['display', 'visibility', 'content', 'opacity', 'transform'] as $informative) {
                self::assertStringNotContainsString(
                    $informative,
                    $rule['body'],
                    "GUEST-RICH-MEDIA-TOUCH-08: `{$informative}` bir SÜS değil, bir bilgidir; imlece bağlanamaz."
                );
            }
        }

        // Ve her `hover` kuralı işaretçisi olan cihaza kapatılmış olmalı.
        self::assertSame(
            preg_match_all('/:hover/i', $css),
            preg_match_all('/:hover/i', implode("\n", $this->hoverGuardedBlocks($css))),
            'GUEST-RICH-MEDIA-TOUCH-08: korumasız bir `:hover`, dokunmalı cihazda takılı kalabilir.'
        );

        preg_match('#<img[^>]*class="qr-menu-item-image"[^>]*>#i', $html, $tag);
        self::assertNotEmpty($tag);
        self::assertStringNotContainsString(
            'title=',
            $tag[0],
            'GUEST-RICH-MEDIA-TOUCH-08: `title` yalnız imleçle görünür; anlatacak şey `alt`ta yaşar.'
        );

        // Fotoğraf hiçbir betik davranışına bağlanmaz: açılan bir katman
        // olsaydı, JavaScript'i çalışmayan bir tarayıcıda ölü bir kutu kalırdı.
        self::assertStringNotContainsString(
            'data-lightbox',
            $html,
            'GUEST-RICH-MEDIA-TOUCH-08: büyütme yolu ürün sayfasıdır, bir katman değil.'
        );
    }

    // --- GUEST-RICH-MEDIA-320-09 -------------------------------------------

    public function test_the_photograph_earns_its_space_on_the_narrowest_screen(): void
    {
        /*
            DAR EKRANDA EN KIT KAYNAK EKRAN ALANIDIR.

            Doğru bileşim "büyük dokunma hedefi + SIKI boşluk"tur; "büyük
            hedef + büyük boşluk" masaüstü ölçeğini dar ekrana kopyalamaktır.
            Fotoğraf bu bütçenin en pahalı kalemidir: 320 px'lik ekranda
            kullanılabilir ~294 px'ten 96 px'i tek başına alır.

            Ölçülen üç şey:

            1. Görsel şeridi 96 px'te KALIR ve kartın kendi genişliğine göre
               büyür (kapsayıcı sorgusu), ekranın genişliğine göre değil.
               Ekran eşiği koysaydık dar bir sütuna konan kart yanlış boyda
               fotoğraf gösterirdi.
            2. Fotoğraf sayfaya İKİNCİ bir kırılma noktası getirmez.
            3. Hiçbir sabit genişlik 320'yi aşmaz ve hiçbir dokunma hedefi
               44 px'in altına inmez.

            Gerçek düzen motorundaki ölçüm ayrı bir kapıdır
            (`scripts/mobile-ux-audit`); burada ölçülen, o ölçümün
            dayandığı kurallardır.
        */
        $granted = $this->guestMenu($this->scene('gorsel-320', [Entitlement::MenuRichMedia])['token']);
        $css = $this->styleBlocks($granted);

        self::assertMatchesRegularExpression(
            '/\.qr-menu-item-image\s*\{[^}]*flex\s*:\s*0\s+0\s+'.self::IMAGE_STRIP_PX.'px/s',
            $css,
            'GUEST-RICH-MEDIA-320-09: taban görsel şeridi '.self::IMAGE_STRIP_PX.' px olmalı.'
        );

        // Fotoğrafın büyümesi KARTIN genişliğine bağlıdır, ekranınkine değil.
        self::assertMatchesRegularExpression(
            '/@container\s*\([^)]*\)\s*\{\s*\.qr-menu-item-image/s',
            $css,
            'GUEST-RICH-MEDIA-320-09: kartın kararı ekranın eşiğine bağlanamaz.'
        );

        preg_match_all('/@media[^{]*\(\s*(?:min|max)-width\s*:\s*([0-9.]+)px/i', $css, $breakpoints);
        self::assertLessThanOrEqual(
            1,
            count($breakpoints[1]),
            'GUEST-RICH-MEDIA-320-09: fotoğraf ikinci bir kırılma noktası getiremez.'
        );

        $widthRules = (string) preg_replace('/@(?:media|container)[^{]*\{/i', '{', $css);
        preg_match_all('/(?<!max-)(?<!-)\b(?:min-width|width|flex-basis)\s*:\s*([0-9.]+)px/i', $widthRules, $widths);

        foreach ($widths[1] as $value) {
            self::assertLessThanOrEqual(
                self::VIEWPORT_320,
                (float) $value,
                "GUEST-RICH-MEDIA-320-09: {$value}px sabit genişlik 320 px'lik ekranı taşırır."
            );
        }

        preg_match_all('/min-(?:height|block-size)\s*:\s*([0-9.]+)px/i', $css, $taps);

        foreach ($taps[1] as $value) {
            self::assertGreaterThanOrEqual(
                self::MIN_TAP_PX,
                (float) $value,
                "GUEST-RICH-MEDIA-320-09: {$value}px dokunma hedefi parmakla vurulamaz."
            );
        }

        /*
            SIKI BOŞLUK: kartın metin sütunu 10/12 px dolguda kalır. Bir
            "nefes alsın" düzeltmesi burada 320'de ürün adını ezerdi.
        */
        self::assertMatchesRegularExpression(
            '/\.qr-menu-item-body\s*\{[^}]*padding\s*:\s*10px\s+12px/s',
            $css,
            'GUEST-RICH-MEDIA-320-09: kart dolgusu dar ekranda sıkı kalmalı.'
        );
    }

    // --- GUEST-RICH-MEDIA-PREVIEW-10 ---------------------------------------

    public function test_the_owner_preview_shows_what_the_guest_will_actually_see(): void
    {
        /*
            ÖNİZLEMENİN TEK İŞİ "MİSAFİR BUNU NASIL GÖRECEK?" SORUSUDUR.

            Sahibin planı fotoğrafı açmıyorsa önizlemede fotoğraf göstermek,
            tam da o soruya yanlış cevap vermektir: sahip yayınlar, masadaki
            misafir fotoğrafı görmez ve aradaki farkı ancak biri sorduğunda
            öğrenir.

            ÖNİZLEMEDE DONMUŞ HAK YOKTUR ve olmamalı: önizleme bir yayın
            değildir; bugün yayınlarsa donacak olan hak bugünün planıdır. Bu
            yüzden ölçüm `forDraftSnapshot` üzerindedir —
            `ShowDraftPreviewController`'ın snapshot'ı şablona verirken
            geçirdiği tek ifade odur. Sahneyi bir medya kütüphanesi kurgusuyla
            şişirmek, ölçülen kararı daha doğru yapmaz; yalnız testi
            kırılganlaştırırdı.
        */
        $scene = $this->scene('gorsel-onizleme', [Entitlement::BrandingCustom]);
        $gate = app(ApplyGuestRichMedia::class);

        $draft = ['categories' => [['name' => 'Sıcak İçecek', 'menuItems' => [[
            'menuItemId' => $scene['menuItemId'],
            'productName' => 'Kahve',
            'priceMinorAmount' => 4250,
            'currencyCode' => 'TRY',
            'allergens' => [],
            'image' => $this->imageBlock(),
        ]]]]];

        $withoutRight = $gate->forDraftSnapshot($scene['workspaceId'], $draft);

        self::assertArrayNotHasKey(
            'image',
            $withoutRight['categories'][0]['menuItems'][0],
            'GUEST-RICH-MEDIA-PREVIEW-10: önizleme, misafirin görmeyeceği bir fotoğrafı göstermez.'
        );

        $this->setLivePlan($scene['workspaceId'], [Entitlement::MenuRichMedia]);

        self::assertArrayHasKey(
            'image',
            $gate->forDraftSnapshot($scene['workspaceId'], $draft)['categories'][0]['menuItems'][0],
            'GUEST-RICH-MEDIA-PREVIEW-10: hak varken önizleme fotoğrafı gösterir.'
        );
    }

    // --- Yardımcılar --------------------------------------------------------

    private function guestMenu(string $token): string
    {
        $response = $this->withHeaders(['Accept' => 'text/html'])->get('/menu/'.$token);
        $response->assertOk();

        return (string) $response->getContent();
    }

    /**
     * `@media (hover: hover)` ile korunmuş blokların gövdeleri.
     *
     * @return list<string>
     */
    private function hoverGuardedBlocks(string $css): array
    {
        preg_match_all('/@media[^{]*\(\s*hover\s*:\s*hover\s*\)[^{]*\{(.*?)\n    \}/s', $css, $matches);

        return $matches[1];
    }

    private function styleBlocks(string $html): string
    {
        preg_match_all('#<style[^>]*>(.*?)</style>#s', $html, $matches);

        return implode("\n", $matches[1]);
    }

    /**
     * Sayfada gerçekten bir FOTOĞRAF çizilmiş mi?
     *
     * Sınıf adı stil bloğunda HER ZAMAN geçer; ona bakmak, fotoğraf hiç
     * çizilmese de "çizildi" derdi. Ölçülen şey `<img>` etiketinin kendisi.
     */
    private function drawsPhotograph(string $html): bool
    {
        return preg_match('#<img[^>]*class="[^"]*qr-(?:menu-item|item)-image#i', $html) === 1;
    }

    /**
     * Ürün sayfasının adresi — sayfanın KENDİ kanonik adresinden türetilir.
     *
     * Slug'ı testte yeniden kurmak, üretimdeki kuralın (marka adı + şube adı)
     * bir kopyasını yazmak olurdu ve o kopya bir gün sessizce eskirdi.
     */
    private function itemPath(string $menuHtml, int $menuItemId): string
    {
        self::assertSame(
            1,
            preg_match('#<link rel="canonical" href="https?://[^/"]+(/[^"]+)"#', $menuHtml, $found),
            'Menü sayfası kanonik adresini basmalı.',
        );

        return $found[1].'/urun/'.$menuItemId.'-kahve';
    }

    /**
     * Canlı planı YAYINDAN SONRA değiştirir — donmuş hakla ayrıştırmak için.
     *
     * @param  list<Entitlement>  $entitlements
     */
    private function setLivePlan(int $workspaceId, array $entitlements): void
    {
        $this->grantEntitlements($workspaceId, $entitlements);
    }

    /**
     * FOTOĞRAFI OLAN TEK ÜRÜNLÜK EN KÜÇÜK GERÇEK SAHNE.
     *
     * `GuestCartTest`in sahnesinden AYRI durur ve öyle kalmalı: orası sepetin
     * çizilip çizilmediğini sınıyor, burası fotoğrafın. Ortak bir kurguya
     * bağlasaydık birinin ihtiyacı diğerinin sahnesini bulandırırdı.
     *
     * ÜRÜNÜN TEK FAZLASI FOTOĞRAFTIR: açıklaması ve alerjeni bilerek yok, ki
     * ürün sayfasının kalite kapısı (05) yalnız fotoğrafa bağlı kalsın.
     *
     * @param  list<Entitlement>  $entitlements
     * @return array{workspaceId:int, menuId:int, token:string, menuItemId:int}
     */
    private function scene(string $seed, array $entitlements): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Restoran '.$seed,
            'slug' => $seed,
            'state' => 'active',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => 'Marka '.$seed,
            'slug' => $seed.'-brand',
            'locale' => 'tr',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId,
            'brand_id' => $brandId,
            'display_name' => 'Şube '.$seed,
            'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul',
            'city' => 'İstanbul',
            'address_line1' => 'Adres '.$seed,
            'accepts_orders' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $publicKey = Str::lower(Str::random(10));

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => $publicKey,
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'name' => 'Ana Menü',
            'state' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $categoryId = (int) DB::table('menu_categories')->insertGetId([
            'menu_id' => $menuId,
            'name' => 'Sıcak İçecek',
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => 'Kahve',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $menuItemId = (int) DB::table('menu_items')->insertGetId([
            'category_id' => $categoryId,
            'product_id' => $productId,
            'price_minor_amount' => 4250,
            'currency_code' => 'TRY',
            'position' => 0,
            'is_visible' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Plan ÖNCE verilir, yayın SONRA yapılır: donan şey yayın anındaki
        // haktır (`docs/114` §3 Dalga 6).
        $this->grantEntitlements($workspaceId, $entitlements);

        $publicationId = (int) DB::table('menu_publications')->insertGetId([
            'workspace_id' => $workspaceId,
            'menu_id' => $menuId,
            'location_id' => $locationId,
            'version' => 1,
            'state' => 'published',
            'snapshot' => json_encode([
                'categories' => [[
                    'name' => 'Sıcak İçecek',
                    'menuItems' => [[
                        'menuItemId' => $menuItemId,
                        'productName' => 'Kahve',
                        'description' => null,
                        'priceMinorAmount' => 4250,
                        'currencyCode' => 'TRY',
                        'allergens' => [],
                        'image' => $this->imageBlock(),
                    ]],
                ]],
            ], JSON_UNESCAPED_UNICODE),
            'entitlements' => json_encode(array_map(
                static fn (Entitlement $entitlement): string => $entitlement->value,
                $entitlements,
            )),
            'published_by' => $owner->id,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menu_publication_current_pointers')->insert([
            'menu_id' => $menuId,
            'current_publication_id' => $publicationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = Str::random(43);

        $qrCodeId = (int) DB::table('qr_codes')->insertGetId([
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'token' => $token,
            'state' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $destinationId = (int) DB::table('qr_destinations')->insertGetId([
            'qr_code_id' => $qrCodeId,
            'destination_type' => 'published_menu',
            'menu_id' => $menuId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('qr_code_current_destinations')->insert([
            'qr_code_id' => $qrCodeId,
            'qr_destination_id' => $destinationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'workspaceId' => $workspaceId,
            'menuId' => $menuId,
            'token' => $token,
            'menuItemId' => $menuItemId,
        ];
    }
}
