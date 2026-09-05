<?php

declare(strict_types=1);

namespace Tests\Feature\QrDestination;

use App\Domain\Entitlement\Entitlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\GrantsPlanEntitlements;
use Tests\TestCase;

/**
 * FF-178 RED — MİSAFİRİN SEPETİ VE SİPARİŞ ONAYI (`docs/115` S3).
 *
 * S2 sunucuda "sipariş nasıl kabul edilir"i çözdü; bu paket masadaki
 * telefonda "sipariş nasıl toplanır"ı çözüyor. Yüzey YALNIZ misafirdir:
 * garson kuyruğu (S4) ve mutfak monitörü (S5) ayrı paketlerdir.
 *
 * ═══ PAKETİN İKİ KEMİK KARARI ═══
 *
 * 1. SEPET CİHAZDA YAŞAR. Sunucuda misafir oturumu YOK ve olmayacak: hiç
 *    sipariş vermeyecek her misafir için satır yazmak, masaya oturan
 *    herkesi veritabanına yazmak olurdu (`docs/115` §2). Sunucuya giden tek
 *    şey GÖNDERİLEN siparişdir.
 *
 * 2. YAPILAMAYAN İŞ ÇİZİLMEZ. Sipariş alma kapalıysa, plan hakkı yoksa ya
 *    da karekod bir masaya bağlı değilse sepet düğmesi HİÇ basılmaz. Çizip
 *    sonra reddetmek, masadaki misafire olmayan bir yetenek göstermektir —
 *    ve bunu restoran değil ürün öder. Kararın sahibi SUNUCUDUR: istemci
 *    "acaba" diye denemez.
 *
 * ═══ RET, TEK BİR "OLMADI" CÜMLESİ DEĞİLDİR ═══
 *
 * S2'nin dört ayrı ret sebebi var ve dördü masadaki misafir için apayrı
 * durumlar: bitmiş bir ürün (sepetten çıkarılır), menüden kalkmış bir ürün
 * (aynı), kapanmış bir mutfak (personele sorulur), masaya bağlı olmayan bir
 * kod (masadaki kod okutulur). Hepsini "sipariş gönderilemedi"ye indirmek,
 * misafiri aynı düğmeye tekrar tekrar bastırırdı.
 *
 * Requirement ID'leri: GUEST-CART-NOT-DRAWN-01, GUEST-CART-DRAWN-02,
 * GUEST-CART-DEVICE-03, GUEST-CART-QUANTITY-04, GUEST-CART-MONEY-05,
 * GUEST-CART-REFUSAL-06, GUEST-CART-RECEIVED-07, GUEST-CART-320-08.
 */
final class GuestCartTest extends TestCase
{
    use GrantsPlanEntitlements;
    use RefreshDatabase;

    /** `docs/48` §1 — 320 px (iPhone 4) gerçek başlangıç noktasıdır. */
    private const VIEWPORT_320 = 320;

    /** Kaynak `--tap` ve depo `--aep-hit-area` aynı değerde buluşur. */
    private const MIN_TAP_PX = 44;

    /**
     * SEPETLİ SAYFANIN BETİK TAVANI — bayt cinsinden.
     *
     * ÖLÇÜM (2026-09-05, aynı sayfa, aynı yöntem):
     *
     * |                          | sepetsiz | sepetli  |
     * | Satır içi betik, ham     | 19 846 B | 35 426 B |
     * | Sayfanın tamamı, ham     | 58 069 B | 87 791 B |
     * | Sayfanın tamamı, gzip    | 16 479 B | 23 427 B |
     * | Dış istek sayısı         |        0 |        0 |
     *
     * Telde ödenen fark ~7 KB'dir ve AĞ İSTEĞİ SAYISI DEĞİŞMEZ. Sepetsiz
     * sayfa bu pakette TEK BAYT bile büyümedi (19 837 → 19 846, yalnız
     * koşul satırlarının boşluğu): işaretleme, stil ve betik yalnız sipariş
     * verilebilen bir masada iniyor. Sipariş almayan bir restoranın
     * misafiri, sahip olmadığı bir yeteneğin bedelini ödemiyor.
     *
     * Ham baytın önemli bir kısmı Türkçe gerekçe yorumlarıdır; sıkıştırmadan
     * sonra bedeli küçüktür ve deponun "neden"i koda yazma kuralını ödemeye
     * değer. Tavan bir HEDEF değil bir SINIRDIR: bir paketlik pay bırakır ve
     * bir kütüphane eklendiği gün kırılır — ki bu paketin kararı tam olarak
     * "kütüphane eklenmez"dir.
     */
    private const MAX_CART_SCRIPT_BYTES = 42000;

    /*
        TAVAN 2026-09-06'DA 38 000 → 42 000 YÜKSELTİLDİ (`docs/122` Y5).

        SEBEP SEPET DEĞİL: favori işaretlemesi TABAN yüzeye girdi ve sepetli
        sayfa da o tabanın üstünde duruyor. Sepetin kendi betiği TEK BAYT
        büyümedi; artan şey her menüde çizilen favori bloğudur ve gerekçesi
        `GuestMenuFindTest::MAX_INLINE_SCRIPT_BYTES` yanında ölçüsüyle
        yazılıdır.

        ÖLÇÜM (aynı sahne, aynı yöntem):

        | | favorisiz | favorili |
        | Satır içi betik, ham | 35 435 B | 39 644 B |
        | Sayfanın tamamı, gzip | 22 922 B | 24 539 B |
        | Dış istek sayısı | 0 | 0 |
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

    /**
     * Sepetin ÇİZİLDİĞİ hâl — sunucunun "bu masa sipariş verebilir" kararı.
     *
     * Şablon bu kararı kendi başına VERMEZ, yalnız uygular; kararın nerede
     * verildiğini yol düzeyindeki iki iddia (01 ve 02) ölçüyor.
     *
     * @param  array<string, mixed>  $extra
     */
    private function render(array $extra = []): string
    {
        return view('public-menu', array_merge([
            'snapshot' => $this->snapshot(),
            'menuKey' => 'demo',
            'ordering' => [
                'submitPath' => '/q/'.str_repeat('a', 43).'/orders',
                'money' => [
                    'prefix' => '₺',
                    'suffix' => '',
                    'decimal' => ',',
                    'group' => '.',
                    'digits' => 2,
                    'zero' => '0',
                ],
            ],
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

    // --- GUEST-CART-NOT-DRAWN-01 ------------------------------------------

    public function test_the_cart_is_never_drawn_where_the_order_could_not_be_taken(): void
    {
        /*
            KARAR SUNUCUDA VERİLİR VE SAYFAYA BASILIR.

            Üç ayrı gerçek, tek bir sonuç: sepet YOK. Sipariş alma kapalıysa
            mutfak kapalıdır; plan hakkı yoksa hizmet satın alınmamıştır;
            karekod masaya bağlı değilse (giriş kodu, afiş) siparişin
            düşeceği masa yoktur. Üçünde de düğmeyi çizip sonra reddetmek,
            misafire iki kez hayır demektir — ikincisi de boşuna bir umuttan
            sonra.
        */
        $closed = $this->scene('sepet-kapali');
        DB::table('locations')->where('id', $closed['locationId'])->update(['accepts_orders' => false]);
        $this->assertNoCartDrawn($closed['token'], 'sipariş alma kapalı');

        // Planı VAR ama içinde `ordering.basic` YOK: menü yayınlanıyor,
        // sipariş alınmıyor. Boş bir plan vermek "abonelik yok"u ölçerdi,
        // oysa sınanan şey hakkın kendisi.
        $unpaid = $this->scene('sepet-haksiz', [Entitlement::BrandingCustom]);
        $this->assertNoCartDrawn($unpaid['token'], 'planda sipariş hakkı yok');

        $tableless = $this->scene('sepet-masasiz');
        DB::table('qr_codes')->where('id', $tableless['qrCodeId'])->update(['dining_table_id' => null]);
        $this->assertNoCartDrawn($tableless['token'], 'karekod bir masaya bağlı değil');
    }

    private function assertNoCartDrawn(string $token, string $why): void
    {
        $html = $this->withHeaders(['Accept' => 'text/html'])->get('/menu/'.$token)->getContent();

        self::assertIsString($html);

        // Menü GİZLENMEZ: kapalı olan sipariştir, restoran değil.
        self::assertStringContainsString('Kahve', $html, "GUEST-CART-NOT-DRAWN-01: {$why} — menü yine de görünmeli.");

        foreach (['data-cart-open', 'data-cart-add', 'data-cart-submit', '/orders'] as $control) {
            self::assertStringNotContainsString(
                $control,
                $html,
                "GUEST-CART-NOT-DRAWN-01: {$why} — `{$control}` çizilemez; yapılamayan iş gösterilmez."
            );
        }
    }

    // --- GUEST-CART-DRAWN-02 ----------------------------------------------

    public function test_a_table_that_can_order_gets_the_cart_and_the_address_to_send_it_to(): void
    {
        /*
            SEPET ANCAK ÜÇ ŞART DA DOĞRUYKEN ÇİZİLİR ve gönderme adresi
            SUNUCUDAN gelir. Adresi istemcide kurmak, karekod belirtecini
            JavaScript'te yeniden birleştirmek olurdu; bir gün biçim
            değişirse sayfa sessizce yanlış uca yazardı.
        */
        $scene = $this->scene('sepet-acik');

        $html = (string) $this->withHeaders(['Accept' => 'text/html'])
            ->get('/menu/'.$scene['token'])
            ->getContent();

        self::assertStringContainsString(
            'data-cart-open',
            $html,
            'GUEST-CART-DRAWN-02: sipariş alan bir masada sepet düğmesi çizilmeli.'
        );

        self::assertStringContainsString(
            'data-cart-add',
            $html,
            'GUEST-CART-DRAWN-02: ürün satırında sepete ekleme çizilmeli.'
        );

        self::assertStringContainsString(
            '/q/'.$scene['token'].'/orders',
            $html,
            'GUEST-CART-DRAWN-02: gönderme adresi sunucudan basılmalı; istemci onu kurmaz.'
        );
    }

    // --- GUEST-CART-DEVICE-03 ---------------------------------------------

    public function test_the_cart_lives_on_the_device_and_the_server_learns_nothing_until_it_is_sent(): void
    {
        /*
            SEPET SUNUCUYA GİTMEZ (`docs/115` §2).

            Sunucuda misafir oturumu yok; sepeti orada tutmak, hiç sipariş
            vermeyecek her misafir için satır yazmak olurdu. Sayfa
            yenilendiğinde sepet durur (M1) çünkü cihazın kendi deposunda
            yaşar — ve o depo çalışmıyorsa (gizli pencere, kapatılmış site
            verisi) sepet düğmesi HİÇ açılmaz: hatırlamadığı bir listeyi
            hatırlıyormuş gibi göstermek, misafirin siparişini sessizce
            kaybetmektir.
        */
        $script = $this->script($this->render());

        self::assertStringContainsString(
            'localStorage',
            $script,
            'GUEST-CART-DEVICE-03: sepet cihazın kendi deposunda yaşamalı.'
        );

        // Gönderilen siparişten BAŞKA hiçbir uca sepet yazılmaz.
        self::assertDoesNotMatchRegularExpression(
            '#/(?:q|api)/[a-z0-9/{}-]*cart#i',
            $script,
            'GUEST-CART-DEVICE-03: sunucuda sepet ucu yok ve olmamalı.'
        );

        self::assertSame(
            1,
            preg_match_all('/\bfetch\(/', $script) - preg_match_all("/fetch\('\/q\/events'/", $script),
            'GUEST-CART-DEVICE-03: sepet için sunucuya atılan tek tur SİPARİŞİN KENDİSİ olmalı.'
        );
    }

    // --- GUEST-CART-QUANTITY-04 -------------------------------------------

    public function test_the_guest_can_change_the_quantity_remove_a_line_and_see_an_empty_cart(): void
    {
        /*
            M2 — adet, çıkarma, toplam. Ve M7: TÜKENMİŞ ÜRÜN SEPETE GİRMEZ.

            "Bugün bitti" işareti sipariş yolunda da geçerlidir: tükenmiş
            satırda ekleme düğmesi HİÇ çizilmez. Çizilseydi misafir onu
            sepete atar, gönderir ve sunucudan `out_of_stock` yerdi — yani
            ürün ona bilerek boşuna bir tur attırırdı.
        */
        $html = $this->render(['outOfStockItemIds' => [101]]);
        $script = $this->script($html);

        foreach (['data-cart-inc', 'data-cart-dec', 'data-cart-remove', 'data-cart-total'] as $control) {
            self::assertStringContainsString(
                $control,
                $html,
                "GUEST-CART-QUANTITY-04: `{$control}` olmadan sepet düzenlenemez."
            );
        }

        // Boş sepetin cümlesi SUNUCUDA basılır, betikte kurulmaz: bir cümle
        // kullanıcı metnidir ve katalogda yaşar (`docs/85`). Betikte
        // kurulsaydı, JavaScript'i çalışmayan bir tarayıcıda da kaybolurdu.
        self::assertMatchesRegularExpression(
            '#<p[^>]*class="[^"]*qr-cart-empty[^"]*"[^>]*>\s*\S+#u',
            $html,
            'GUEST-CART-QUANTITY-04: boş sepetin kendi cümlesi olmalı; boş bir kutu bir cümle değildir.'
        );

        // Tükenmiş satırda (101) ekleme yok, tükenmemişte (102) var.
        preg_match('#<li[^>]*id="item-101".*?</li>#s', $html, $soldOutRow);
        self::assertNotEmpty($soldOutRow);
        self::assertStringNotContainsString(
            'data-cart-add',
            $soldOutRow[0],
            'GUEST-CART-QUANTITY-04: tükenmiş ürün sepete eklenemez (M7).'
        );

        preg_match('#<li[^>]*id="item-102".*?</li>#s', $html, $availableRow);
        self::assertNotEmpty($availableRow);
        self::assertStringContainsString('data-cart-add', $availableRow[0]);
    }

    // --- GUEST-CART-MONEY-05 ----------------------------------------------

    public function test_the_total_is_written_in_the_shape_the_canonical_formatter_produced(): void
    {
        /*
            PARA BİÇİMİ İSTEMCİDE UYDURULMAZ (M2).

            Toplam misafirin telefonunda toplanır — ama BİÇİMİ orada
            doğmaz: ondalık basamak sayısı, ayırıcılar ve para birimi
            işareti `MoneyFormatter`'ın ürettiği bir kalıptan gelir. Sabit
            100'e bölmek yende ve dinarda yanlış fiyat üretir ve fiyat,
            restoranın misafirine verdiği taahhüttür (`docs/13` §4).

            Bu yüzden istemcide ikinci bir biçimlendirici KURULMAZ:
            `Intl.NumberFormat` sunucununkinden başka bir cevap verebilir ve
            aradaki fark ancak masada hesap istendiğinde anlaşılırdı.
        */
        $html = $this->render();
        $script = $this->script($html);

        self::assertStringContainsString(
            '"digits":2',
            $html,
            'GUEST-CART-MONEY-05: ondalık basamak sunucudan gelmeli.'
        );

        self::assertStringNotContainsString(
            'Intl.NumberFormat',
            $script,
            'GUEST-CART-MONEY-05: istemcide ikinci bir para biçimlendiricisi kurulamaz.'
        );

        self::assertDoesNotMatchRegularExpression(
            '/\/\s*100\b/',
            $script,
            'GUEST-CART-MONEY-05: sabit 100 yende ve dinarda yanlış fiyat üretir.'
        );

        self::assertMatchesRegularExpression(
            '/data-item-price-minor="18500"/',
            $html,
            'GUEST-CART-MONEY-05: toplam kuruştan toplanmalı; ekrandaki metinden ayrıştırmak biçime bağımlı olurdu.'
        );
    }

    // --- GUEST-CART-REFUSAL-06 --------------------------------------------

    public function test_every_refusal_reason_the_endpoint_can_return_gets_its_own_sentence(): void
    {
        /*
            DÖRT SEBEP, DÖRT CÜMLE — ARTI ÖDENMEMİŞ HAK.

            `StoreGuestOrderController` bunları ayrı ayrı döndürüyor ve
            masadaki misafir için dördü apayrı durum: bitmiş bir ürünü
            sepetten çıkarır, kapanmış bir mutfakta personele sorar, masaya
            bağlı olmayan bir kodda masadaki kodu okutur. Tek bir "sipariş
            gönderilemedi" cümlesi, misafiri aynı düğmeye tekrar bastırırdı.

            HANGİ ÜRÜN olduğu da söylenir: sepette beş satır varken "bir şey
            bitmiş" demek, misafire sepeti tek tek denetletirdi — uç zaten
            `menuItemId` gönderiyor.
        */
        $script = $this->script($this->render());

        $reasons = [
            'out_of_stock' => 'refusedOutOfStock',
            'item_unavailable' => 'refusedItemUnavailable',
            'ordering_closed' => 'refusedOrderingClosed',
            'table_unknown' => 'refusedTableUnknown',
            'entitlement_required' => 'refusedEntitlementRequired',
        ];

        foreach ($reasons as $reason => $key) {
            self::assertStringContainsString(
                "'".$reason."'",
                $script,
                "GUEST-CART-REFUSAL-06: `{$reason}` sebebi tanınmalı."
            );

            self::assertStringContainsString(
                "'".$key."'",
                $script,
                "GUEST-CART-REFUSAL-06: `{$reason}` kendi cümlesini almalı."
            );
        }

        self::assertStringContainsString(
            'menuItemId',
            $script,
            'GUEST-CART-REFUSAL-06: reddedilen satırın hangisi olduğu söylenmeli.'
        );
    }

    // --- GUEST-CART-RECEIVED-07 -------------------------------------------

    public function test_a_received_order_says_the_waiter_still_has_to_confirm_it(): void
    {
        /*
            201 "MUTFAK BAŞLADI" DEMEK DEĞİLDİR (`docs/115` §2).

            Misafirin gönderdiği bir TALEP, garsonun onayladığı bir İŞtir.
            Ekranın "siparişin alındı" deyip orada durması, masada oturan
            kişiye mutfağın çalışmaya başladığını söylerdi; oysa henüz
            kimse ona bakmadı. UYDURMA SÜRE de yok (M4): kaç dakikada
            geleceğini bilmiyoruz ve bilmediğimizi yazmayız.

            SEPET ANCAK 201'DE TEMİZLENİR. Ret sonrası temizlemek, misafirin
            tek tek seçtiği listeyi düzeltemeyeceği bir anda silmek olurdu.
        */
        $script = $this->script($this->render());

        self::assertStringContainsString(
            "say('orderPlaced')",
            $script,
            'GUEST-CART-RECEIVED-07: alınan sipariş kendi cümlesini almalı.'
        );

        self::assertMatchesRegularExpression(
            '/status\s*===?\s*201/',
            $script,
            'GUEST-CART-RECEIVED-07: sepet yalnız sunucu 201 dediğinde temizlenmeli.'
        );

        foreach (['dakika', 'minute', 'ETA'] as $promise) {
            self::assertStringNotContainsString(
                $promise,
                $script,
                "GUEST-CART-RECEIVED-07: `{$promise}` ölçülmemiş bir süre sözüdür (M4)."
            );
        }
    }

    // --- GUEST-CART-320-08 -------------------------------------------------

    public function test_the_cart_fits_the_smallest_screen_and_the_weakest_line(): void
    {
        /*
            320 ÖNCE, VE YENİ BİR EŞİK YOK.

            `docs/113` §7.1: bu dosyada tek bir kırılma noktası var ve
            sepet onu ikiye çıkarmaz. Kaynağın sabit alt sepet çubuğu da
            EKLENMEZ: sayfadaki tek yapışkan öğe kimlik başlığıdır ve
            320×480'de ikinci bir sabit çubuk dikey alanın önemli bir
            kısmını yerdi (`docs/48` §6.5 — hiçbir denetim içeriğin üstüne
            KALICI binmez). Sepet, misafirin kendi açtığı bir katmandır.
        */
        $css = $this->styleBlocks($this->render());

        preg_match_all('/@media[^{]*\(\s*(?:min|max)-width\s*:\s*([0-9.]+)px/i', $css, $breakpoints);

        self::assertCount(
            1,
            $breakpoints[1],
            'GUEST-CART-320-08: sepet ikinci bir kırılma noktası getiremez.'
        );

        $widthRules = (string) preg_replace('/@(?:media|container)[^{]*\{/i', '{', $css);
        preg_match_all('/(?<!max-)(?<!-)\b(?:min-width|width|flex-basis)\s*:\s*([0-9.]+)px/i', $widthRules, $widths);

        foreach ($widths[1] as $value) {
            self::assertLessThanOrEqual(
                self::VIEWPORT_320,
                (float) $value,
                "GUEST-CART-320-08: {$value}px sabit genişlik 320 px'lik ekranı taşırır."
            );
        }

        preg_match_all('/min-(?:height|block-size)\s*:\s*([0-9.]+)px/i', $css, $taps);

        foreach ($taps[1] as $value) {
            self::assertGreaterThanOrEqual(
                self::MIN_TAP_PX,
                (float) $value,
                "GUEST-CART-320-08: {$value}px dokunma hedefi parmakla vurulamaz."
            );
        }

        // Sepetin kendi denetimleri de ortak dokunma ölçüsünü kullanır.
        self::assertMatchesRegularExpression(
            '/\.qr-cart-step\s+button\s*\{[^}]*min-(?:height|block-size)\s*:\s*var\(--qr-tap\)/s',
            $css,
            'GUEST-CART-320-08: adet düğmeleri ortak dokunma ölçüsünü kullanmalı.'
        );

        /*
            EN KÜÇÜK EKRAN İLE EN ZAYIF HAT AYNI MİSAFİRDİR.

            Bu sayfa masada, çoğu zaman zayıf bir hücresel bağlantıda
            açılıyor. Sepet JavaScript istiyor; istenen şey ölçülür ve bir
            tavana bağlanır. `GuestMenuFindTest`teki tavan SEPETSİZ sayfayı
            koruyor ve o sayfa bu pakette TEK BAYT bile büyümedi — sepetin
            işaretlemesi, stili ve betiği yalnız sipariş verilebilen bir
            masada iniyor. Buradaki tavan, sepetin kendi bedelidir.

            KÜTÜPHANE EKLENMEZ: bir kütüphane bu tavanı tek başına aşar. Ham
            baytın önemli bir kısmı Türkçe gerekçe yorumlarıdır ve
            sıkıştırmadan sonra bedeli küçüktür.
        */
        $bytes = strlen($this->script($this->render()));

        self::assertLessThanOrEqual(
            self::MAX_CART_SCRIPT_BYTES,
            $bytes,
            "GUEST-CART-320-08: sepetli sayfanın satır içi betiği {$bytes} bayt; tavan aşıldı."
        );
    }

    /**
     * Sipariş verebilen bir masanın en küçük gerçek sahnesi.
     *
     * `GuestOrderSubmissionTest`'in sahnesinden AYRI durur ve öyle kalmalı:
     * orası ucun sözleşmesini sınıyor, burası sayfanın çizip çizmediğini.
     * Ortak bir kurguya bağlasaydık, birinin ihtiyacı diğerinin sahnesini
     * bulandırırdı.
     *
     * @param  list<Entitlement>|null  $entitlements
     * @return array{workspaceId:int, locationId:int, qrCodeId:int, token:string}
     */
    private function scene(string $seed, ?array $entitlements = null): array
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
            'accepts_orders' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)),
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
                        'priceMinorAmount' => 4250,
                        'currencyCode' => 'TRY',
                        'allergens' => [],
                    ]],
                ]],
            ]),
            'entitlements' => json_encode(array_map(
                static fn (Entitlement $entitlement): string => $entitlement->value,
                $entitlements ?? Entitlement::cases(),
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

        $areaId = (int) DB::table('dining_areas')->insertGetId([
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'label' => 'Salon',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tableId = (int) DB::table('dining_tables')->insertGetId([
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'area_id' => $areaId,
            'name' => 'Masa 12',
            'seat_count' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = Str::random(43);

        $qrCodeId = (int) DB::table('qr_codes')->insertGetId([
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'dining_table_id' => $tableId,
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
            'locationId' => $locationId,
            'qrCodeId' => $qrCodeId,
            'token' => $token,
        ];
    }
}
