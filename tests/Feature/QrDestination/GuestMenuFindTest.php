<?php

declare(strict_types=1);

namespace Tests\Feature\QrDestination;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FF-177 RED — misafir menüsünde BULMAK (`docs/114` §3, Dalga 2).
 *
 * Üç yetenek tek pakette geliyor çünkü üçü de aynı listeyi süzüyor ve ayrı
 * ayrı yazılsalardı üç ayrı "hangi ürünler görünür" kararı doğardı: arama,
 * sesli arama ve filtreler. Bir listede iki karar sahibi olması, birinin
 * diğerini sessizce ezmesi demektir.
 *
 * ÖLÇÜLEN KARAR — ARAMA İSTEMCİDE KALIR. Yayın anlık görüntüsünün TAMAMI
 * zaten sunucuda basılıyor: 80 ürünlük bir menü 149 KB ham / 15 KB gzip
 * iniyor ve her ürünün adı, açıklaması, fiyatı ve alerjeni DOM'da duruyor.
 * Misafirin telefonundaki bir metin eşleşmesi için sunucuya gitmek, elde
 * olan veriyi ikinci kez istemek olurdu — masadaki zayıf hücresel bağlantıda
 * her tuş vuruşunda bir tur. Bu yüzden bu pakette SUNUCU TARAFI ARAMA UCU
 * AÇILMIYOR ve aşağıdaki ilk iddia o kararı ölçülebilir tutuyor.
 *
 * Requirement ID'leri: GUEST-SEARCH-CLIENT-01, GUEST-SEARCH-SIGNAL-02,
 * GUEST-VOICE-SUPPORTED-ONLY-03, GUEST-VOICE-NO-AUDIO-04,
 * GUEST-VOICE-ON-PRESS-05, GUEST-VOICE-HEADER-06, GUEST-FILTER-AXES-07,
 * GUEST-FILTER-NO-CLAIM-08, GUEST-FILTER-HONEST-EMPTY-09,
 * GUEST-JS-BUDGET-10.
 */
final class GuestMenuFindTest extends TestCase
{
    use RefreshDatabase;

    /**
     * MİSAFİR YÜZEYİNİN BETİK BÜTÇESİ — bayt cinsinden, tavan olarak.
     *
     * ÖLÇÜM (2026-09-05, aynı sayfa, aynı yöntem):
     *
     * | | FF-175 sonu | FF-177 sonu |
     * | Satır içi betik, ham | 9 732 B | 19 023 B |
     * | Aynı betik, yorumsuz | — | 13 450 B |
     * | Aynı betik, gzip | — | 5 685 B |
     * | 80 ürünlük sayfa, gzip | 15 214 B | 20 312 B |
     * | Dış istek sayısı | 0 | 0 |
     *
     * Yani telde ödenen fark ~5 KB'dir ve ağ isteği sayısı DEĞİŞMEZ (bkz.
     * `GuestMenuDesignLanguageTest`). Ham baytın yarısına yakını Türkçe
     * gerekçe yorumlarıdır; sıkıştırmadan sonra bedeli küçüktür ve deponun
     * "neden"i koda yazma kuralını ödemeye değer.
     *
     * Tavan bir HEDEF değil bir SINIRDIR: bugünkü ölçümün üstünde bir
     * paketlik pay bırakır ve bir kütüphane eklendiği gün kırılır — ki bu
     * paketin kararı tam olarak "kütüphane eklenmez"dir.
     */
    private const MAX_INLINE_SCRIPT_BYTES = 26000;

    /*
        TAVAN 2026-09-06'DA 22 000 → 26 000 YÜKSELTİLDİ (`docs/122` Y5).

        SEBEP: misafir menüsüne favori işaretlemesi girdi ve favori HER
        menüde çizilir — sepet gibi bir sunucu kararına bağlı değildir,
        kararı yalnız misafirin cihazı verir. Bu yüzden bedeli taban
        yüzeyde ödeniyor ve tavan orada yükseliyor.

        ÖLÇÜM (aynı sahne, aynı yöntem):

        | | favorisiz | favorili |
        | Satır içi betik, ham | 19 855 B | 24 064 B |
        | Sayfanın tamamı, ham | 60 394 B | 67 358 B |
        | Sayfanın tamamı, gzip | 16 682 B | 18 511 B |
        | Dış istek sayısı | 0 | 0 |

        TELDE ÖDENEN FARK ~1,8 KB ve AĞ İSTEĞİ SAYISI DEĞİŞMEZ. Ham baytın
        önemli bir kısmı Türkçe gerekçe yorumlarıdır; sıkıştırmadan sonra
        bedeli küçüktür. Kütüphane eklenmedi ve kalp satır içi tek bir SVG
        sembolüdür — bir simge paketi bu tavanı tek başına aşardı.

        Tavan bir HEDEF değil bir SINIRDIR: bugünkü ölçümün üstünde bir
        paketlik pay bırakır ve bir kütüphane eklendiği gün kırılır.
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
                            'allergens' => ['Gluten', 'Süt'],
                        ],
                        [
                            'menuItemId' => 102,
                            'productName' => 'Karides Güveç',
                            'priceMinorAmount' => 32000,
                            'currencyCode' => 'TRY',
                            'allergens' => ['Kabuklu deniz ürünleri'],
                        ],
                        [
                            'menuItemId' => 103,
                            'productName' => 'Mevsim Salata',
                            'priceMinorAmount' => 9000,
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

    // --- GUEST-SEARCH-CLIENT-01 -------------------------------------------

    public function test_the_whole_menu_is_already_on_the_client_so_search_needs_no_round_trip(): void
    {
        /*
            KARARIN ÖLÇÜSÜ. Aranabilir her alan — ad, açıklama, fiyat,
            alerjen — sunucunun bastığı işaretlemede zaten var. Bir arama
            ucu eklemek, misafire elinde olanı ikinci kez indirtirdi.

            Bu iddia bir gün DEĞİŞEBİLİR: menü sayfalanmaya başladığı gün
            (ürünlerin bir kısmı istemciye hiç inmediğinde) sunucu tarafı
            arama meşru olur. O gün bu test kırılır ve karar yeniden
            verilir — sessizce eskimez.
        */
        $html = $this->render();

        foreach (['Adana Kebap', 'Karides Güveç', 'Mevsim Salata'] as $name) {
            self::assertStringContainsString(
                $name,
                $html,
                "GUEST-SEARCH-CLIENT-01: `{$name}` istemciye inmemiş; arama sunucuya gitmek zorunda kalır."
            );
        }

        // Arama İSTEĞİ yok: sayfa aramak için hiçbir uca bağlanmıyor.
        self::assertDoesNotMatchRegularExpression(
            '#/(?:q|api)/[a-z-]*search#i',
            $html,
            'GUEST-SEARCH-CLIENT-01: arama için sunucuya tur atılmamalı; menü zaten istemcide.'
        );
    }

    public function test_every_filterable_field_is_printed_beside_the_row_it_belongs_to(): void
    {
        // Filtre de arama gibi istemcide çalışır ve bunun tek şartı verinin
        // satırın YANINDA olmasıdır: alerjen ve fiyat, kartın kendi
        // özniteliğinde durur.
        $html = $this->render();

        self::assertMatchesRegularExpression(
            '/data-item-allergens="[^"]*gluten[^"]*"/iu',
            $html,
            'GUEST-FILTER-AXES-07: alerjen ekseni satırın kendi özniteliğinden okunmalı.'
        );

        self::assertMatchesRegularExpression(
            '/data-item-price="185(?:[.,]0+)?"/',
            $html,
            'GUEST-FILTER-AXES-07: fiyat ekseni misafirin GÖRDÜĞÜ birimde basılmalı (kuruş değil).'
        );
    }

    // --- GUEST-SEARCH-SIGNAL-02 -------------------------------------------

    public function test_the_not_found_signal_still_leaves_the_page_and_still_means_search(): void
    {
        /*
            "ARANIP BULUNAMAYAN" DEFTERİ BOZULMAZ (`docs/84`).

            Bu olay panelde sahibin göremediği tek talebi besliyor: menüde
            OLMAYAN şeyin talebi. Filtreler geldiğinde en kolay hata şu
            olurdu — misafir alerjen filtresi yüzünden sıfır sonuç görür ve
            sunucuya "karides aradı, bulamadı" yazılır. Oysa karides menüde
            VARDIR; misafir onu kendi filtresiyle elemiştir.

            Bu yüzden sinyal yalnız ARAMA EKSENİNDEN sayılan sonuç üzerinden
            basılır — filtreler o sayıya karışmaz.
        */
        $script = $this->script($this->render());

        self::assertStringContainsString(
            "'search_no_results'",
            $script,
            'GUEST-SEARCH-SIGNAL-02: sonuç bulunamadı olayı hâlâ basılmalı.'
        );

        self::assertMatchesRegularExpression(
            '/reportNoResults\(\s*query\s*,\s*searchOnlyCount\s*\)/',
            $script,
            'GUEST-SEARCH-SIGNAL-02: sinyal FİLTRELENMİŞ sayıdan değil, yalnız arama ekseninden sayılmalı.'
        );
    }

    // --- GUEST-VOICE-SUPPORTED-ONLY-03 ------------------------------------

    public function test_the_microphone_button_is_never_drawn_by_the_server(): void
    {
        /*
            DESTEKLEMEYEN TARAYICIDA DÜĞME ÇİZİLMEZ.

            Konuşma tanıma bir tarayıcı yeteneğidir ve sunucu isteğe bakarak
            onu bilemez — kullanıcı aracısına bakarak tahmin etmek, bu
            depoda zaten bir kez yanlış çıkmış bir yöntemdir. Bu yüzden
            düğme sunucuda ÇİZİLMEZ: işaretlemesi inert bir `<template>`
            içinde iner ve DOM'a yalnız yetenek gerçekten varsa girer.

            Çalışmayan bir mikrofon düğmesi, masadaki misafire olmayan bir
            yetenek vaat eder.
        */
        $html = $this->render();

        $withoutTemplates = (string) preg_replace('#<template\b.*?</template>#is', '', $html);

        self::assertStringNotContainsString(
            'id="menu-voice"',
            $withoutTemplates,
            'GUEST-VOICE-SUPPORTED-ONLY-03: mikrofon düğmesi sunucu çıktısında ÇİZİLİ olamaz.'
        );

        self::assertStringContainsString(
            '<template id="menu-voice-template"',
            $html,
            'GUEST-VOICE-SUPPORTED-ONLY-03: düğmenin işaretlemesi katalogla birlikte inmeli, çizilmeden.'
        );

        $script = $this->script($html);

        self::assertMatchesRegularExpression(
            '/window\.SpeechRecognition\s*\|\|\s*window\.webkitSpeechRecognition/',
            $script,
            'GUEST-VOICE-SUPPORTED-ONLY-03: yetenek sorgusu iki adı da tanımalı.'
        );
    }

    // --- GUEST-VOICE-NO-AUDIO-04 ------------------------------------------

    public function test_voice_search_never_carries_audio_to_the_server(): void
    {
        /*
            SES SUNUCUYA GİTMEZ (`docs/114` §3).

            Ses kaydı kişisel veridir. Tarayıcının kendi tanıyıcısı metni
            üretir, ürün metni arar; kayıt hiçbir zaman bizim sunucumuza
            ulaşmaz. Bu bir performans tercihi değil, bir veri kararıdır ve
            kaydı taşımak çözdüğünden çok sorun getirirdi.
        */
        $script = $this->script($this->render());

        foreach (['getUserMedia', 'MediaRecorder', 'AudioContext', 'FormData'] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $script,
                "GUEST-VOICE-NO-AUDIO-04: `{$forbidden}` sesin sunucuya taşınabileceği anlamına gelir."
            );
        }
    }

    // --- GUEST-VOICE-ON-PRESS-05 ------------------------------------------

    public function test_the_microphone_permission_is_asked_only_when_the_guest_presses_it(): void
    {
        /*
            İZİN SAYFA AÇILIŞINDA İSTENMEZ.

            Karekodu okutan misafir menüye bakmak istiyor. Sayfa açılır
            açılmaz mikrofon izni sormak, sormadığı bir soruya cevap
            istemektir ve çoğu misafir onu reddeder — sonra gerçekten
            kullanmak istediğinde de artık soramayız.

            İzin isteğini doğuran satır `recognition.start()`tır; bu iddia
            onun tek olduğunu ve tıklama işleyicisinden SONRA geldiğini
            ölçer.
        */
        $script = $this->script($this->render());

        $starts = preg_match_all('/recognition\.start\(\)/', $script);
        self::assertSame(
            1,
            $starts,
            'GUEST-VOICE-ON-PRESS-05: tanıma tek bir yerden başlatılmalı.'
        );

        $clickAt = strpos($script, "voiceButton.addEventListener('click'");
        $startAt = strpos($script, 'recognition.start()');

        self::assertIsInt($clickAt, 'GUEST-VOICE-ON-PRESS-05: mikrofon düğmesinin tıklama işleyicisi yok.');
        self::assertIsInt($startAt);
        self::assertGreaterThan(
            $clickAt,
            $startAt,
            'GUEST-VOICE-ON-PRESS-05: izin yalnız düğmeye basıldığında istenmeli.'
        );
    }

    public function test_a_refused_microphone_is_said_in_a_sentence_and_not_swallowed(): void
    {
        // Sessiz başarısızlık yok: misafir düğmeye basar, hiçbir şey olmaz
        // ve neden olmadığını bilmez — bu, düğmenin hiç olmamasından kötüdür.
        $html = $this->render();
        $script = $this->script($html);

        self::assertStringContainsString(
            "say('voiceDenied')",
            $script,
            'GUEST-VOICE-ON-PRESS-05: izin reddedildiğinde dürüst bir cümle kurulmalı.'
        );

        self::assertStringContainsString(
            'id="menu-voice-status"',
            $html,
            'GUEST-VOICE-ON-PRESS-05: cümlenin duyurulacağı bir canlı bölge olmalı.'
        );
    }

    // --- GUEST-VOICE-HEADER-06 --------------------------------------------

    public function test_the_microphone_is_opened_on_the_guest_menu_and_stays_shut_everywhere_else(): void
    {
        /*
            GÜVENLİK BAŞLIĞI DAR AÇILIR.

            `Permissions-Policy` bugün mikrofonu HER yüzeyde kapatıyor ve
            sesli arama o kapının arkasında kalıyordu. Kapı bu pakette
            açılıyor ama yalnız misafir menüsü için ve yalnız KENDİ
            kaynağımıza: `microphone=(self)`. Panelde, kimlik yüzeylerinde
            ve kurumsal sayfalarda mikrofon kapalı kalır — orada isteyecek
            bir şey yok, dolayısıyla açık bırakmanın kazancı da yok.
        */
        $home = $this->get('/');

        self::assertStringContainsString(
            'microphone=()',
            (string) $home->headers->get('Permissions-Policy'),
            'GUEST-VOICE-HEADER-06: misafir menüsü dışında mikrofon kapalı kalmalı.'
        );

        /*
            KARAR ROTANIN KENDİSİNDE VERİLİR, sayfanın içeriğinde değil.

            Bu yüzden iddia yayınlanmış bir menü KURMAZ: menüsü olmayan bir
            karekod adresi de aynı rotadır ve başlık kararı orada da aynı
            çıkmalıdır. Menü içeriğine bağlansaydı, başlık bir gün "menü
            boşsa mikrofon kapalı" gibi görünmez bir davranış kazanırdı.
        */
        $token = str_repeat('a', 43);

        $policy = (string) $this->withHeaders(['Accept' => 'text/html'])
            ->get("/menu/{$token}")
            ->headers->get('Permissions-Policy');

        self::assertStringContainsString(
            'microphone=(self)',
            $policy,
            'GUEST-VOICE-HEADER-06: sesli arama misafir menüsünde çalışabilmeli.'
        );

        self::assertStringContainsString(
            'camera=()',
            $policy,
            'GUEST-VOICE-HEADER-06: açılan tek şey mikrofon olmalı; başlık toptan gevşetilmez.'
        );

        // Aynı kökteki ÇIKMAZ SOKAK misafir menüsü DEĞİLDİR: orada arama
        // kutusu yok, dolayısıyla mikrofon da yok.
        self::assertStringContainsString(
            'microphone=()',
            (string) $this->withHeaders(['Accept' => 'text/html'])
                ->get('/menu/bilinmeyen-bir-adres/parca')
                ->headers->get('Permissions-Policy'),
            'GUEST-VOICE-HEADER-06: kapı yalnız menü sayfası için açılmalı.'
        );
    }

    // --- GUEST-FILTER-AXES-07 ---------------------------------------------

    public function test_the_filter_offers_only_the_allergens_this_menu_actually_declares(): void
    {
        /*
            EKSEN VERİDEN DOĞAR, LİSTEDEN DEĞİL.

            Sabit bir alerjen listesi çizmek, bu menüde hiç geçmeyen bir
            alerjeni misafire seçtirir ve o seçim hiçbir şeyi elemez —
            misafir filtrenin bozuk olduğunu sanır. Çipler yalnız bu yayının
            BİLDİRDİĞİ alerjenlerden kurulur.
        */
        $html = $this->render();

        foreach (['Gluten', 'Süt', 'Kabuklu deniz ürünleri'] as $declared) {
            self::assertMatchesRegularExpression(
                '/<button[^>]*data-allergen-filter[^>]*>\s*'.preg_quote($declared, '/').'\s*</u',
                $html,
                "GUEST-FILTER-AXES-07: bildirilen `{$declared}` filtre ekseninde yok."
            );
        }

        // Bildirilmemiş bir alerjen çip olarak çizilmez.
        self::assertDoesNotMatchRegularExpression(
            '/data-allergen-filter="[^"]*yer fıstığı/iu',
            $html,
            'GUEST-FILTER-AXES-07: bu menüde bildirilmemiş bir alerjen seçenek olarak sunulamaz.'
        );

        // Fiyat ekseni: iki uçlu ve sayısal.
        self::assertStringContainsString('id="filter-price-min"', $html);
        self::assertStringContainsString('id="filter-price-max"', $html);
    }

    public function test_the_filter_panel_is_not_offered_where_it_cannot_work(): void
    {
        // Filtreleme JavaScript ister. Betik çalışmıyorsa panel AÇILMAZ:
        // basıldığında hiçbir şey elemeyen bir denetim, çalışmayan bir
        // mikrofon düğmesiyle aynı yalanı söyler.
        $html = $this->render();

        self::assertMatchesRegularExpression(
            '/<details[^>]*\bdata-filters\b[^>]*\bhidden\b|<details[^>]*\bhidden\b[^>]*\bdata-filters\b/',
            $html,
            'GUEST-FILTER-AXES-07: panel sunucuda gizli iner, onu JavaScript açar.'
        );

        self::assertMatchesRegularExpression(
            '/filters\.hidden\s*=\s*false/',
            $this->script($html),
            'GUEST-FILTER-AXES-07: paneli açan yer betiğin kendisi olmalı.'
        );
    }

    // --- GUEST-FILTER-NO-CLAIM-08 -----------------------------------------

    public function test_the_allergen_filter_excludes_and_never_claims_the_dish_is_free_of_anything(): void
    {
        /*
            BU BİR KAPSAM KARARI DEĞİL, GÜVENLİK KARARIDIR (`docs/114` §0).

            Ürün "bu üründe fıstık YOK" diyemez; söyleyebileceği tek şey
            "restoran fıstık BİLDİRMEDİ"dir. Filtre bu yüzden yalnız HARİÇ
            TUTMA yönünde kurulur ve panelin kendi cümlesi bunu misafire
            açıkça söyler — çünkü boşalan bir liste, misafirin kalanları
            güvenli sanmasına yol açabilir.

            `GuestMenuDesignLanguageTest` iddianın alan adı biçimini
            donduruyor; bu iddia da misafire GÖRÜNEN cümleyi donduruyor.
        */
        $html = $this->render();

        self::assertMatchesRegularExpression(
            '/<p[^>]*class="[^"]*qr-filter-hint[^"]*"[^>]*>\s*\S+/u',
            $html,
            'GUEST-FILTER-NO-CLAIM-08: alerjen ekseni kendi sınırını yazıyla söylemeli.'
        );

        foreach (['allergen_free', 'no_allergens', 'is_vegan_certified', 'cross_contamination'] as $claim) {
            self::assertStringNotContainsString($claim, $html);
        }

        // Filtre "içermez" yönünde kurulamaz: eleme yönü işaretlemede de
        // okunur olmalı.
        self::assertStringContainsString(
            'data-allergen-filter',
            $html,
            'GUEST-FILTER-NO-CLAIM-08: eksen hariç tutma yönünde adlandırılmalı.'
        );
    }

    // --- GUEST-FILTER-HONEST-EMPTY-09 -------------------------------------

    public function test_an_empty_filter_result_says_something_different_from_an_empty_search(): void
    {
        /*
            SIFIR SONUÇ "BÖYLE BİR ŞEY YOK" DEMEK DEĞİLDİR.

            Aramada sıfır sonuç "menüde bu yok" anlamına gelir ve sahibin
            defterine yazılır. Filtrede sıfır sonuç ise "senin koyduğun
            sınırlara uyan yok" anlamına gelir — menü doludur. İki cümleyi
            tek cümleye indirmek, misafire menünün boş olduğunu söylerdi.
        */
        $script = $this->script($this->render());

        self::assertStringContainsString(
            "say('filterNoMatch')",
            $script,
            'GUEST-FILTER-HONEST-EMPTY-09: filtre boşluğunun kendi cümlesi olmalı.'
        );

        self::assertStringContainsString(
            "say('searchNoMatch')",
            $script,
            'GUEST-FILTER-HONEST-EMPTY-09: arama boşluğunun cümlesi yerinde kalmalı.'
        );
    }

    // --- GUEST-JS-BUDGET-10 -----------------------------------------------

    public function test_the_guest_surface_pays_for_the_new_capabilities_in_bytes_it_can_afford(): void
    {
        /*
            BAYT ÖLÇÜLÜR, TAHMİN EDİLMEZ.

            Bu sayfa masada, çoğu zaman zayıf bir hücresel bağlantıda
            açılıyor. Sesli arama ve filtreler JavaScript istiyor; istenen
            şey ölçülür ve bir tavana bağlanır. Kütüphane eklenmez — bir
            kütüphane bu tavanı tek başına aşar.
        */
        $bytes = strlen($this->script($this->render()));

        self::assertLessThanOrEqual(
            self::MAX_INLINE_SCRIPT_BYTES,
            $bytes,
            "GUEST-JS-BUDGET-10: satır içi betik {$bytes} bayt; misafir yüzeyinin tavanı aşıldı."
        );
    }
}
