<?php

declare(strict_types=1);

namespace Tests\Feature\QrDestination;

use App\Application\Publication\Dto\GuestClosedNotice;
use Tests\TestCase;

/**
 * FF-175 RED — misafir menüsü kaynağın tasarım diline taşınır (`docs/113`).
 *
 * KAYNAK: `docs/reference/guest-menu/lezzet-sarayi.dc.html`. Sahibin kuralı
 * geçerlidir: tasarımı sahip veriyorsa kaynak kazanır.
 *
 * KAYNAK YALNIZ UI'DIR. Sepet, puanlama, favori, filtreler, fotoğraf anahtarı
 * ve sesli arama YAPILACAK; arka uçları ve headless bağlantıları hemen
 * ardından gelen paketlerin işidir ve bir kısmı fiyat kademesine bağlanacak.
 * Bu paketin sınırı şudur: düzen o parçaları sonradan EŞİKSİZ kaldırabilecek
 * biçimde kurulur, ama verisi gelmeden hiçbiri ÇİZİLMEZ — çizilmiş ama
 * hiçbir şey yapmayan bir düğme, masadaki misafire çalışacağını söyleyip
 * yalan söyler.
 *
 * Kaynağın kapsamına ise depodaki dört yetenek hiç girmiyor (dil seçici,
 * kapalı şerit, bugün tükendi, çevrimdışı/PWA); kaynağa körü körüne
 * uyulsaydı sessizce kaybolurlardı. Bu dosya iki yönü birden dondurur.
 *
 * Bu dosya bir görünüm testidir ve GÖRÜNTÜYÜ iddia edemez; ölçebildiği şey
 * işaretlemenin ve stil bloğunun taşıdığı KARARLARDIR. Bir ekran görüntüsü
 * karşılaştırması bu depoda yok ve burada varmış gibi yapılmaz.
 *
 * Requirement ID'leri: GUEST-320-ONE-BREAKPOINT-01, GUEST-320-NO-CRUSH-02,
 * GUEST-TAP-44-03, GUEST-ZABUNO-FRAME-04, GUEST-ZERO-REQUEST-05,
 * GUEST-KEEP-FOUR-06, GUEST-DRAW-ONLY-WHAT-EXISTS-07.
 */
final class GuestMenuDesignLanguageTest extends TestCase
{
    /**
     * `docs/48` §1: 320 px (iPhone 4) gerçek başlangıç noktasıdır.
     * `docs/113` §7.2: 320'de gövde yatay dolgusu düşünce 296 px kalır.
     */
    private const VIEWPORT_320 = 320;

    /** `--aep-bp-md` (`resources/css/aep/tokens/layout.css:6`) — deponun kendi ölçeği. */
    private const ONLY_BREAKPOINT_PX = 1024;

    /** Kaynak `--tap` ve depo `--aep-hit-area` aynı değerde buluşuyor (`docs/113` §2.1). */
    private const MIN_TAP_PX = 44;

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'identity' => [
                'brandName' => 'Lezzet Sarayı',
                'locationName' => 'Kadıköy',
                'addressLine' => 'Bahariye Caddesi No: 1, Kadıköy/İstanbul',
                'phone' => '+90 216 000 00 00',
                'primaryColor' => '#c9481b',
                'secondaryColor' => '#e8a020',
            ],
            'categories' => [
                [
                    'name' => 'Kebaplar',
                    'menuItems' => [
                        [
                            'menuItemId' => 101,
                            'productName' => 'Fırında Kuzu Tandır Pilav Üstü',
                            'priceMinorAmount' => 18500,
                            'currencyCode' => 'TRY',
                            'description' => 'Kömür ateşinde pişirilmiş, yanında bulgur pilavı ile.',
                            'allergens' => ['gluten', 'süt'],
                        ],
                        [
                            'menuItemId' => 102,
                            'productName' => 'Adana Kebap',
                            'priceMinorAmount' => 16000,
                            'currencyCode' => 'TRY',
                            'allergens' => [],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** @param  array<string, mixed>  $extra */
    private function render(array $extra = []): string
    {
        return view('public-menu', array_merge([
            'snapshot' => $this->snapshot(),
            'menuKey' => 'demo',
        ], $extra))->render();
    }

    /**
     * Sayfanın TEK stil bloğu — çerçevenin sözü budur (`docs/113` §6.3):
     * `--qr-*` beş dosyada değil, bir yerde tanımlanır.
     */
    private function styleBlocks(string $html): string
    {
        preg_match_all('#<style[^>]*>(.*?)</style>#s', $html, $matches);

        return implode("\n", $matches[1]);
    }

    // --- GUEST-320-ONE-BREAKPOINT-01 --------------------------------------

    public function test_the_guest_menu_keeps_exactly_one_breakpoint_and_it_is_the_page_shell_decision(): void
    {
        /*
            KAYNAKTA ALTI EŞİK VAR (375 / 430 / 600 / 1024 / 1280 / 1600) ve
            beşi birbirini eziyor: aynı kart hem 375'te hem 430'da hem 600'de
            yeniden karar veriyor. `docs/48` §3 araç sırası bunu yasaklamıyor,
            ama sıraya koyuyor — kırılma noktası EN SONDUR.

            `docs/113` §7.1 beşini içsel düzene ve kapsayıcı sorgusuna
            çeviriyor; geriye YALNIZ sayfa iskeleti kararı kalıyor: yan rayın
            içeriğin YANINDA mı yoksa ÜSTÜNDE mi durduğu. Bu gerçekten
            ekranın kararıdır, kartın değil.

            Bu iddia eski "hiç kırılma noktası olmasın" kuralından daha
            SIKIDIR: sayıyı da sabitler. Bir gün ikinci bir eşik eklenirse ya
            da bu eşik deponun ölçeğinden (1024 = `--aep-bp-md`) kayarsa,
            burada kırılır.
        */
        $css = $this->styleBlocks($this->render());

        preg_match_all('/@media[^{]*\(\s*(?:min|max)-width\s*:\s*([0-9.]+)px/i', $css, $matches);

        self::assertCount(
            1,
            $matches[1],
            'GUEST-320-ONE-BREAKPOINT-01: misafir menüsünde tek bir kırılma noktası olmalı; '
            .'kalanlar içsel düzene ve kapsayıcı sorgusuna çevrilir (`docs/113` §7.1). '
            .'Bulunan: '.implode(', ', $matches[1])
        );

        self::assertSame(
            (float) self::ONLY_BREAKPOINT_PX,
            (float) $matches[1][0],
            'GUEST-320-ONE-BREAKPOINT-01: kalan eşik deponun kendi ölçeğindeki '
            .'`--aep-bp-md` ile aynı olmalı; uydurma bir sayı ölçek değildir.'
        );
    }

    public function test_column_counts_come_from_intrinsic_layout_not_from_thresholds(): void
    {
        // Kaynağın 600 / 1280 / 1600 eşikleri sütun SAYISI için vardı.
        // `auto-fit` onları üretir: sütun sayısı eşikten değil, taban
        // genişlikten çıkar ve dar bir sütunun içinde de doğru davranır.
        $css = $this->styleBlocks($this->render());

        self::assertMatchesRegularExpression(
            '/repeat\(\s*auto-fit\s*,\s*minmax\(/i',
            $css,
            'GUEST-320-ONE-BREAKPOINT-01: sütun sayısı `repeat(auto-fit, minmax(…))` ile üretilmeli.'
        );

        self::assertMatchesRegularExpression(
            '/@container[^{]*\(/i',
            $css,
            'GUEST-320-ONE-BREAKPOINT-01: kart içi kararlar EKRANI değil KAPSAYICIYI dinlemeli (`docs/48` §3, 2. araç).'
        );
    }

    // --- GUEST-320-NO-CRUSH-02 --------------------------------------------

    public function test_no_guest_rule_pins_a_width_wider_than_the_smallest_screen(): void
    {
        /*
            `docs/48` §6.3: 320 pikselden geniş sabit genişlik yok. İki şey
            kapsam DIŞIDIR ve ikisi de bilerek:

            - `max-width` bir SABİT değil bir TAVANDIR; akışkanlığı bozmaz.
            - `@media` / `@container` sorgu başlıklarındaki eşik bir ÖLÇÜ
              değil bir SORUdUR ("kapsayıcı bu kadar geniş mi?"). Onları da
              saymak, kırılma noktası kararını genişlik ihlali sanmak olurdu.
        */
        $css = (string) preg_replace('/@(?:media|container)[^{]*\{/i', '{', $this->styleBlocks($this->render()));

        preg_match_all('/(?<!max-)(?<!-)\b(?:min-width|width|flex-basis)\s*:\s*([0-9.]+)px/i', $css, $matches);

        foreach ($matches[1] as $value) {
            self::assertLessThanOrEqual(
                self::VIEWPORT_320,
                (float) $value,
                "GUEST-320-NO-CRUSH-02: {$value}px sabit genişlik 320 px'lik ekranı taşırır (`docs/48` §6.3)."
            );
        }
    }

    public function test_the_result_count_never_shares_a_line_with_fixed_width_controls(): void
    {
        /*
            KAYNAĞIN ÖLÇÜLMÜŞ BİRİNCİ KIRIĞI (`docs/113` §7.2.1).

            Kaynağın filtre çubuğunda sabitler 320'de ~293 px yiyor ve sonuç
            etiketine ~3 px kalıyor. Etiket `min-width:0` taşıdığı için
            TAŞMIYOR — sessizce KAYBOLUYOR. Yani "kaç ürün gösteriliyor"
            bilgisi tam da en küçük ekranda yok oluyor; hiçbir taşma testi de
            bunu yakalamıyor.

            Çözüm eşik değil, YERLEŞİM: sayı sabit denetimlerin yanından
            çıkar, listenin başında kendi satırını alır. Bu iddia o kararı
            dondurur — özet yapışkan başlığın İÇİNDE olamaz ve kendi satırını
            (`flex-basis:100%`) alır.
        */
        $html = $this->render();

        preg_match('#<header[^>]*class="[^"]*qr-hdr[^"]*"[^>]*>(.*?)</header>#s', $html, $header);

        self::assertNotEmpty($header, 'GUEST-320-NO-CRUSH-02: kaynağın yapışkan başlığı bekleniyor.');
        self::assertStringNotContainsString(
            'qr-menu-summary',
            $header[1],
            'GUEST-320-NO-CRUSH-02: sonuç sayısı sabit genişlikli denetimlerin yanına konamaz; '
            .'320\'de 3 px\'e sıkışıp kaybolur (`docs/113` §7.2.1).'
        );

        self::assertMatchesRegularExpression(
            '/\.qr-menu-summary\s*\{[^}]*flex\s*:[^;}]*100%/s',
            $this->styleBlocks($html),
            'GUEST-320-NO-CRUSH-02: özet satırı kendi satırını almalı (`flex: … 100%`).'
        );
    }

    public function test_a_priced_sold_out_item_still_leaves_room_for_its_own_name(): void
    {
        /*
            DEPONUN KENDİ İKİNCİ KIRIĞI — kaynağın alt çubuğuyla AYNI
            aritmetik (`docs/113` §7.2.2).

            Kaynakta ürün sayfasının alt çubuğunda adet denetimi ~125 px
            yiyor ve eylem düğmesine 153 px kalıyor; metin kesiliyor. O çubuk
            SEPETE bağlıdır ve §13 gereği çizilmiyor. Ama aynı aritmetik
            deponun BUGÜN çizdiği satırda da var: 320'de kullanılabilir
            ~294 px'ten görsel 96 + fiyat ~58 + "Bugün tükendi" rozeti ~104 +
            üç boşluk 36 çıkınca ürün ADINA 0 px kalıyor.

            Çözüm yine yerleşim: durum cümlesi kendi satırını alır (bir rozet
            değil, bir CÜMLEDİR) ve ad ile fiyat sarmalanan bir satırı
            paylaşır. Ad hiçbir genişlikte sıfıra inmez.
        */
        $css = $this->styleBlocks($this->render(['outOfStockItemIds' => [101]]));

        self::assertMatchesRegularExpression(
            '/\.qr-menu-item-sold-out-note\s*\{[^}]*flex\s*:[^;}]*100%/s',
            $css,
            'GUEST-320-NO-CRUSH-02: "bugün tükendi" bir rozet gibi ada rakip olamaz; kendi satırını alır.'
        );

        self::assertMatchesRegularExpression(
            '/\.qr-menu-item-name\s*\{[^}]*flex\s*:\s*1\s+1\s+/s',
            $css,
            'GUEST-320-NO-CRUSH-02: ürün adı esneyen ve sarmalanabilen bir taban genişlik taşımalı.'
        );
    }

    // --- GUEST-TAP-44-03 ---------------------------------------------------

    public function test_no_control_declares_a_touch_target_below_44_pixels(): void
    {
        // Kaynağın `--tap` değeri ile deponun `--aep-hit-area` değeri aynı
        // yerde buluşuyor (`docs/113` §2.1). Yoğunluk misafir yüzeyinde
        // AYARLANAMAZ (`docs/113` §5.3): 44 px bir taban, bir tercih değil.
        $css = $this->styleBlocks($this->render());

        self::assertMatchesRegularExpression(
            '/--qr-tap\s*:\s*'.self::MIN_TAP_PX.'px/',
            $css,
            'GUEST-TAP-44-03: dokunma hedefi tek bir yerde tanımlanmalı.'
        );

        preg_match_all('/min-(?:height|block-size)\s*:\s*([0-9.]+)px/i', $css, $matches);

        foreach ($matches[1] as $value) {
            self::assertGreaterThanOrEqual(
                self::MIN_TAP_PX,
                (float) $value,
                "GUEST-TAP-44-03: {$value}px dokunma hedefi parmakla vurulamaz."
            );
        }
    }

    // --- GUEST-ZABUNO-FRAME-04 --------------------------------------------

    public function test_the_zabuno_frame_wraps_the_surface_and_produces_no_box_today(): void
    {
        /*
            GÖRÜNMEZ ÇERÇEVE (`docs/113` §6).

            Çerçeve bugün hiçbir piksel üretmez ama yarın zabuno başlığının
            ve altbilgisinin gireceği yeri BELİRLER. Görünmezliği bir
            `display:none` değildir — `display:contents` ile kutu hiç
            OLUŞMAZ: yapışkan başlığın `top` hesabı, `z-index` sırası ve grid
            akışı çerçeveden hiç etkilenmez.
        */
        $html = $this->render();

        self::assertSame(
            1,
            substr_count($html, 'data-zabuno-surface='),
            'GUEST-ZABUNO-FRAME-04: yüzeyi saran TEK bir zabuno sınırı olmalı.'
        );

        self::assertMatchesRegularExpression(
            '/\[data-zabuno\][^{]*\{[^}]*display\s*:\s*contents/s',
            $this->styleBlocks($html),
            'GUEST-ZABUNO-FRAME-04: çerçeve kutu üretmemeli (`display:contents`).'
        );
    }

    public function test_an_empty_frame_slot_emits_no_node_at_all(): void
    {
        // Boş bir `<div>` bırakmak "görünmez" değil "görünmeyen" olurdu:
        // yapışkan başlığın `top` hesabını kirletir ve ekran okuyucuda
        // boş bir bölge bırakırdı (`docs/113` §6.1.2).
        self::assertStringNotContainsString(
            'data-zabuno-slot',
            $this->render(),
            'GUEST-ZABUNO-FRAME-04: dolu olmayan yuva çıktıda HİÇ bulunmamalı.'
        );
    }

    // --- GUEST-ZERO-REQUEST-05 --------------------------------------------

    public function test_the_frame_does_not_cost_the_guest_a_single_extra_request(): void
    {
        /*
            `docs/113` §6.3'ün ölçülebilir kabul ölçütü: çerçeve girdikten
            sonra misafir sayfasının istek sayısı ve JS baytı DEĞİŞMEMELİ.

            Bugün ikisi de sıfır: sayfa ne paket ne derlenmiş CSS yüklüyor.
            Paket bölmenin maliyeti bu yüzden negatiftir (`docs/113` §8):
            0 bayttan küçük bir paket yoktur.
        */
        $html = $this->render();

        self::assertDoesNotMatchRegularExpression(
            '/<script[^>]+\bsrc=/i',
            $html,
            'GUEST-ZERO-REQUEST-05: misafir sayfası dış betik yüklemez.'
        );

        self::assertDoesNotMatchRegularExpression(
            '/<link[^>]+rel="stylesheet"/i',
            $html,
            'GUEST-ZERO-REQUEST-05: misafir sayfası dış stil dosyası yüklemez.'
        );

        self::assertStringNotContainsString(
            '/build/assets',
            $html,
            'GUEST-ZERO-REQUEST-05: misafir yüzeyinde paket bölünmez (`docs/113` §8).'
        );
    }

    // --- GUEST-KEEP-FOUR-06 -----------------------------------------------

    public function test_the_four_capabilities_the_source_never_had_survive_the_redesign(): void
    {
        /*
            PLANIN EN DEĞERLİ BULGUSU (`docs/113` §4.1).

            Kaynağa körü körüne uyulsaydı bu dört yetenek sessizce
            kaybolurdu: kaynakta olmamaları bir eksiklik değil, kaynağın
            kapsamının dar olmasıdır. Sessizce kaybolan bir yetenek, hiç
            yapılmamış bir yetenekten pahalıdır — çünkü bir gün birinin onu
            aradığı ana kadar kimse fark etmez.
        */
        $html = $this->render([
            'guestLocale' => 'en',
            'contentLocale' => 'tr',
            'outOfStockItemIds' => [101],
            'closedNotice' => new GuestClosedNotice('09:00', 1, false),
        ]);

        // 1 — misafirin dil seçimi (`docs/85`).
        self::assertMatchesRegularExpression(
            '#<nav[^>]+class="[^"]*qr-menu-language#',
            $html,
            'GUEST-KEEP-FOUR-06: dil seçici kayboldu.'
        );

        // 2 — şube kapalı / servis dışı bildirimi (FF-141/FF-143).
        self::assertStringContainsString(
            'data-guest-state="closed"',
            $html,
            'GUEST-KEEP-FOUR-06: kapalı şerit kayboldu.'
        );

        // 3 — "bugün tükendi" (`docs/82`).
        self::assertStringContainsString(
            'qr-menu-item-sold-out',
            $html,
            'GUEST-KEEP-FOUR-06: "bugün tükendi" kayboldu.'
        );

        // 4 — çevrimdışı / PWA kurulumu.
        self::assertStringContainsString('id="pwa-install-button"', $html);
        self::assertStringContainsString('id="pwa-offline-status"', $html);
        self::assertStringContainsString('/public-diner-sw.js', $html);
    }

    public function test_the_sold_out_state_is_said_in_words_and_not_only_in_colour(): void
    {
        // WCAG 1.4.1 — renk tek başına anlatmaz. Solukluk YARDIMCIDIR;
        // cümlenin kendisi metindir.
        $html = $this->render(['outOfStockItemIds' => [101]]);

        self::assertMatchesRegularExpression(
            '#<[a-z]+[^>]*class="[^"]*qr-menu-item-sold-out-note[^"]*"[^>]*>\s*\S+#u',
            $html,
            'GUEST-KEEP-FOUR-06: tükendi durumu METİNLE söylenmeli.'
        );
    }

    // --- GUEST-DRAW-ONLY-WHAT-EXISTS-07 -----------------------------------

    public function test_no_control_is_drawn_before_the_data_that_makes_it_work(): void
    {
        /*
            ÇİZİM SIRASI: veri önce, düğme sonra.

            Bu bir kalıcı yasak listesi DEĞİLDİR. Aşağıdaki her parça
            yapılacak — sepet, puanlama, favori, sesli arama, diyet filtresi,
            fotoğraf anahtarı — ve her biri kendi arka ucuyla birlikte, ayrı
            paketlerde gelecek; bir kısmı fiyat kademesine de bağlanacak.

            Bu paketin sınırı yalnız SIRAYI korumak. Bir "Sepete ekle" düğmesi
            çizmek onu çalışır yapmaz, yalnız misafire çalışacağını söyler:
            masadaki misafir basar, hiçbir şey olmaz ve bunu restoran değil
            ürün öder. Düzen o parçaları eşiksiz kaldıracak biçimde kuruldu
            (bkz. kart gövdesi ve yardımcı çubuk yorumları); eksik olan tek
            şey veridir.

            Bir parça arka ucuyla geldiğinde buradaki satırı SİLİNİR ve o
            paketin kendi testi devralır.
        */
        $html = $this->render();

        $notYetWired = [
            // Sepet/sipariş — arka ucu ayrı pakette.
            'ph-handbag' => 'sepet düğmesi',
            'data-cart' => 'sepet durumu',
            'qr-cartbar' => 'sepet çubuğu',
            // Puanlama — arka ucu ayrı pakette.
            'ph-star' => 'yıldız/puan',
            'data-rating' => 'puan verisi',
            // Favori — istemci kalıcılığı ayrı pakette.
            'data-favourite' => 'favori',
            /*
                SESLİ ARAMA BU LİSTEDEN ÇIKTI (FF-177).

                Yetenek arka ucuyla birlikte geldi: güvenlik başlığı misafir
                menüsü için `microphone=(self)`e açıldı, düğme yalnız
                tarayıcı konuşma tanımayı destekliyorsa çiziliyor ve
                `GuestMenuFindTest` bu kuralların sahibi oldu. Satırı burada
                bırakmak, teslim edilmiş bir yeteneği yasaklamak olurdu.

                İKON YAZI TİPİ YASAĞI KALIYOR ve sebebi ayrıdır: `ph-*`
                sınıfları Phosphor yazı tipini ister, o da bir AĞ İSTEĞİDİR
                ve bu sayfanın ölçülen sözü sıfır istektir (`docs/113` §8).
                Mikrofon simgesi bu yüzden satır içi SVG'dir.
            */
            'ph-microphone' => 'ikon yazı tipi',
            // Diyet filtresi — arka ucu ayrı pakette. FF-177'nin getirdiği
            // alerjen ekseni bu değildir: o HARİÇ TUTAR, diyet ise bir
            // İDDİADIR ("vegan") ve verisi yok.
            'data-diet' => 'diyet filtresi',
        ];

        foreach ($notYetWired as $needle => $what) {
            self::assertStringNotContainsString(
                $needle,
                $html,
                "GUEST-DRAW-ONLY-WHAT-EXISTS-07: {$what} arka ucuyla birlikte gelir; verisi bağlanmadan çizilmez."
            );
        }
    }

    public function test_a_health_claim_is_never_drawn_no_matter_which_package_ships_next(): void
    {
        /*
            BU BİR KAPSAM KARARI DEĞİL, GÜVENLİK KARARIDIR — ve öyle kalır.

            Diyet filtresi kendi paketiyle geldiğinde bile bu iddia yerinde
            durur: `ArtifactSchemaValidator` `allergen_free`, `no_allergens`,
            `cross_contamination`, `is_vegan_certified` gibi alan adlarını ADA
            GÖRE reddediyor ve gerekçesi kodda yazılı — yanlış bir
            alerjensizlik iddiası bir SAĞLIK OLAYIDIR. Filtre "içerir" yönünde
            (hariç tutma) kurulabilir; "içermez" yönünde kurulamaz.
        */
        $html = $this->render();

        foreach (['allergen_free', 'no_allergens', 'cross_contamination', 'is_vegan_certified'] as $claim) {
            self::assertStringNotContainsString(
                $claim,
                $html,
                "GUEST-DRAW-ONLY-WHAT-EXISTS-07: \"{$claim}\" biçiminde bir alerjensizlik iddiası hiçbir pakette çizilmez."
            );
        }
    }

    public function test_the_page_makes_no_claim_the_data_it_carries_cannot_support(): void
    {
        /*
            "Fiyatlar KDV dahildir" ve karttaki "520 kcal" kaynakta duruyor.
            Bu paketin taşıdığı ürün verisinde vergi ve besin değeri alanı
            bulunmuyor; alanlar kendi paketleriyle geldiğinde bu satırlar da
            gelir. O güne kadar yazılmazlar, çünkü beslenme değeri ve vergi
            beyanı HUKUKİ iddialardır: arkasında veri olmadan yazılan bir
            iddia doğru çıkarsa şans, yanlış çıkarsa ürünün sorumluluğudur.
        */
        $html = $this->render();

        self::assertStringNotContainsString('KDV', $html);
        self::assertDoesNotMatchRegularExpression('/kcal|kalori/iu', $html);
    }
}
