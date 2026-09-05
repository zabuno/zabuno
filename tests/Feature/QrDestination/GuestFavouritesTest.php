<?php

declare(strict_types=1);

namespace Tests\Feature\QrDestination;

use Tests\TestCase;

/**
 * Y5 RED — MİSAFİRİN FAVORİLERİ, CİHAZDA (`docs/122` Y5, `docs/114` Dalga 3).
 *
 * ═══ KARAR ZATEN VERİLMİŞ VE GEREKÇESİ BELGEDE ═══
 *
 * `docs/114` Dalga 3 üç seçeneği tartıp birini seçti: favori CİHAZDA yaşar.
 * Hesap istemek karekod menünün bütün vaadini bozar; ziyaretçi anahtarına
 * yazmak ise anahtar günlük döndüğü için favoriyi de günlük kaybeder —
 * kalıcı yapmak takibi kalıcı yapmak demektir. Bedeli telefon değişince
 * kaybolmasıdır ve bu kabul edilmiştir: favori bir kolaylıktır, bir varlık
 * değil.
 *
 * Bu testin işi o kararı KODA DONDURMAKTIR. Bir gün biri "favorileri
 * sunucuda tutalım" derse, üç iddia birden kırılır ve karar yeniden
 * verilir — sessizce eskimez.
 *
 * ═══ HATIRLAMADIĞI BİR LİSTEYİ HATIRLIYORMUŞ GİBİ GÖSTERMEK YANLIŞ ═══
 *
 * Gizli pencerede ya da site verisi kapatılmış bir tarayıcıda cihaz deposu
 * çalışmaz. Orada favori düğmesi HİÇ ÇİZİLMEZ: `hidden` ile iner ve betik
 * onu ancak depo gerçekten yazıp okuyabildiğinde açar. Sepette aynı karar
 * aynı gerekçeyle alınmıştı.
 *
 * Requirement ID'leri: GUEST-FAV-DEVICE-ONLY-01, GUEST-FAV-NO-SERVER-02,
 * GUEST-FAV-NO-STORAGE-NO-BUTTON-03, GUEST-FAV-320-04,
 * GUEST-FAV-PER-MENU-05, GUEST-FAV-JS-BUDGET-06.
 */
final class GuestFavouritesTest extends TestCase
{
    /** `docs/48` §1 — 320 px (iPhone 4) gerçek başlangıç noktasıdır. */
    private const MIN_TAP_PX = 44;

    /*
        BAYT TAVANI BU DOSYADA DEĞİL.

        Favori, ölçülen taban yüzeye giriyor ve tavanı zaten
        `GuestMenuFindTest::MAX_INLINE_SCRIPT_BYTES` tutuyor. İkinci bir tavan
        koymak, birinin bir gün sessizce gevşemesi demekti.
    */

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'categories' => [
                [
                    'name' => 'Kebaplar',
                    'menuItems' => [
                        [
                            'menuItemId' => 101,
                            'productName' => 'Adana Kebap',
                            'priceMinorAmount' => 18500,
                            'currencyCode' => 'TRY',
                            'allergens' => ['Gluten'],
                        ],
                        [
                            'menuItemId' => 102,
                            'productName' => 'Karides Güveç',
                            'priceMinorAmount' => 32000,
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

    /** Sayfanın yürüyen betiği — JSON veri bloğu betik değildir. */
    private function script(string $html): string
    {
        preg_match_all(
            '#<script\b(?![^>]*application/(?:ld\+json|json))[^>]*>(.*?)</script>#is',
            $html,
            $matches,
        );

        return implode("\n", $matches[1]);
    }

    private function styleBlocks(string $html): string
    {
        preg_match_all('#<style[^>]*>(.*?)</style>#s', $html, $matches);

        return implode("\n", $matches[1]);
    }

    // --- GUEST-FAV-DEVICE-ONLY-01 -----------------------------------------

    public function test_every_dish_carries_a_favourite_toggle(): void
    {
        $html = $this->render();

        self::assertSame(
            2,
            preg_match_all('#<button[^>]*\bdata-favourite\b#', $html),
            'GUEST-FAV-DEVICE-ONLY-01: her ürün satırı kendi favori düğmesini taşımalı.'
        );

        // Basılı / basılı değil ayrımı ekran okuyucuda `aria-pressed`tedir;
        // yalnız renkle anlatmak, renk göremeyen misafire hiçbir şey anlatmaz.
        self::assertMatchesRegularExpression(
            '#<button[^>]*data-favourite[^>]*aria-pressed="false"#',
            $html,
            'GUEST-FAV-DEVICE-ONLY-01: favori düğmesi basılı olup olmadığını SÖYLEMELİ.'
        );
    }

    public function test_the_favourite_lives_in_the_device_store_and_says_so_in_code(): void
    {
        $script = $this->script($this->render());

        self::assertStringContainsString(
            'localStorage',
            $script,
            'GUEST-FAV-DEVICE-ONLY-01: favori cihaz deposunda yaşar (`docs/114` Dalga 3).'
        );
    }

    // --- GUEST-FAV-NO-SERVER-02 -------------------------------------------

    public function test_marking_a_favourite_never_reaches_the_server(): void
    {
        /*
            SUNUCUYA HİÇBİR ŞEY GİTMEZ ve ziyaretçi anahtarına da YAZILMAZ.

            Bu iddia sayfanın tamamına bakamaz — sepet ve puanlama kendi
            uçlarına gider ve gitmelidir. Bakılan şey FAVORİ BLOĞUDUR: o blok
            içinde ne bir `fetch`, ne bir `sendBeacon`, ne bir form vardır.
        */
        /*
            YORUMLAR SÖKÜLÜR, ÇÜNKÜ SORU KODA SORULUYOR.

            Blok başlığı "bu blok sunucuya bir şey göndermez" diye yazıyor ve
            o cümlenin içinde yasaklı adların kendisi geçiyor. Yorumu da
            tarayan bir kural, kuralı AÇIKLAMAYI yasaklardı — ve o kuralın
            ilk kurbanı, neden öyle olduğunu yazan cümle olurdu.
        */
        $block = $this->withoutComments($this->favouriteBlock($this->script($this->render())));

        foreach (['fetch(', 'sendBeacon', 'XMLHttpRequest', 'visitor'] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $block,
                "GUEST-FAV-NO-SERVER-02: favori bloğunda `{$forbidden}` bulundu; ".
                'favori cihazda yaşar, sunucuda değil.'
            );
        }
    }

    // --- GUEST-FAV-NO-STORAGE-NO-BUTTON-03 --------------------------------

    public function test_the_button_ships_hidden_so_a_broken_store_never_shows_a_list_it_cannot_keep(): void
    {
        $html = $this->render();

        self::assertMatchesRegularExpression(
            '#<button[^>]*data-favourite[^>]*\shidden\b#',
            $html,
            'GUEST-FAV-NO-STORAGE-NO-BUTTON-03: favori düğmesi GİZLİ iner; onu betik açar.'
        );

        $block = $this->favouriteBlock($this->script($html));

        // Depo yoklaması: yazıp okuyamadığı bir depoda betik ÇIKAR ve düğme
        // gizli kalır. Yalnız `window.localStorage`ın varlığına bakmak
        // yetmez — gizli pencerede nesne vardır, yazma atar.
        self::assertMatchesRegularExpression(
            '/try\s*\{[^}]*setItem[^}]*\}\s*catch[^{]*\{[^}]*return/s',
            $block,
            'GUEST-FAV-NO-STORAGE-NO-BUTTON-03: depo yazılabilir mi diye DENENMELİ ve '.
            'denemeyi geçemezse düğme hiç açılmamalı.'
        );
    }

    // --- GUEST-FAV-320-04 --------------------------------------------------

    public function test_the_favourite_target_is_a_finger_and_not_a_pixel(): void
    {
        $styles = $this->styleBlocks($this->render());

        self::assertMatchesRegularExpression(
            '/\.qr-fav\s*\{[^}]*min-height:\s*var\(--qr-tap\)/s',
            $styles,
            'GUEST-FAV-320-04: favori düğmesi ortak parmak ölçüsünü kullanmalı.'
        );

        self::assertMatchesRegularExpression(
            '/--qr-tap:\s*'.self::MIN_TAP_PX.'px/',
            $styles,
            'GUEST-FAV-320-04: ortak parmak ölçüsü '.self::MIN_TAP_PX.' px olmalı.'
        );
    }

    public function test_the_page_adds_no_library_for_a_heart(): void
    {
        $html = $this->render();

        // Satır içi SVG: bir ikon için paket indirmek, masadaki misafire
        // beklemesi gereken bir istek daha eklemektir.
        self::assertStringContainsString(
            '#qr-heart',
            $html,
            'GUEST-FAV-320-04: kalp satır içi SVG sembolünden gelmeli.'
        );

        self::assertDoesNotMatchRegularExpression(
            '/<script[^>]+\bsrc=/i',
            $html,
            'GUEST-FAV-320-04: misafir sayfası dış betik yüklemez.'
        );
    }

    // --- GUEST-FAV-PER-MENU-05 --------------------------------------------

    public function test_favourites_belong_to_one_menu_and_not_to_the_whole_phone(): void
    {
        /*
            Aynı restoranın iki menüsü ayrı favori tutar. Tutmasaydı,
            kahvaltıda işaretlenen bir ürün akşam menüsünde ortaya çıkardı —
            ya da daha kötüsü, başka bir restoranın menüsünde.
        */
        $block = $this->favouriteBlock($this->script($this->render()));

        self::assertStringContainsString(
            'menuKey',
            $block,
            'GUEST-FAV-PER-MENU-05: favori anahtarı MENÜYE bağlı olmalı.'
        );
    }

    /**
     * Betiğin yalnız FAVORİ bloğu.
     *
     * Sayfanın tamamına bakan bir iddia, sepetin kendi `fetch`ini görüp
     * yanlış yerde alarm verirdi. Blok kendi işaretiyle bulunur: sınır
     * kodun içinde yazılıdır, testin tahmininde değil.
     */
    private function favouriteBlock(string $script): string
    {
        $start = strpos($script, '/* ═══ FAVORİLER');
        self::assertNotFalse($start, 'Favori bloğu işaretlenmemiş.');

        $end = strpos($script, '/* ═══ FAVORİLER SONU');
        self::assertNotFalse($end, 'Favori bloğunun sonu işaretlenmemiş.');

        return substr($script, $start, $end - $start);
    }

    /** Yorumsuz hâli — iddianın konusu koddur, kodun gerekçesi değil. */
    private function withoutComments(string $source): string
    {
        return (string) preg_replace(['#/\*.*?\*/#s', '#//[^\n]*#'], '', $source);
    }
}
