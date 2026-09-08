<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Support\Localization\SiteText;
use Database\Seeders\PlanCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FF-239 RED — fiyat sayfası gerçek planlar ve SSS ile (`docs/107` Faz 2.7,
 * `docs/139`).
 *
 * MÜŞTERİ SORUNU. Telefonundan bakan, acelesi olan bir restoran sahibi fiyat
 * sayfasında karar verir. `docs/88` rakamı katalogdan okumayı çözmüştü ve
 * `docs/90` kademeleri uygulanandan türetmişti; geriye sayfanın METNİ
 * kalmıştı ve üç soru cevapsızdı:
 *
 *   1. "Bunlardan hangisi BENİM?" — kademe adı ("Team") bunu söylemez.
 *   2. "Parasını ödeyince masada ne DEĞİŞMEYECEK?" — tik dolu bir tablo
 *      yalnız pahalı sütunun neye sahip olduğunu söyler; ödedikten sonra
 *      hâlâ olmayacak şeyi söylemez.
 *   3. "Çıkmak istersem ne olur?" — cevabı sözleşmenin içindeydi, yani
 *      ödeme adımından SONRA öğrenilirdi.
 *
 * Bu kapı üçünün de sayfada durduğunu ve UYDURULMADIĞINI ölçer.
 *
 * Requirement IDs: PRICING-AUDIENCE-STATED-01, PRICING-AUDIENCE-FROM-CATALOG-01,
 * PRICING-EXCLUSIONS-STATED-01, PRICING-FAQ-ANSWERS-REAL-QUESTIONS-01,
 * PRICING-EXIT-PATH-LINKED-01, PRICING-NO-INVENTED-CLAIM-01,
 * PRICING-MOBILE-FIRST-01.
 */
final class PricingPageContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Tohum AÇIKÇA çağrılır: fiyat ŞEMA DEĞİLDİR, iş verisidir (`docs/90`).
        $this->seed(PlanCatalogueSeeder::class);
    }

    private function html(): string
    {
        return (string) $this->get('/pricing')->assertOk()->getContent();
    }

    // --- PRICING-AUDIENCE-STATED-01 ---------------------------------------

    /**
     * "Pro" bir şey anlatmaz.
     *
     * Kademe adı, telefonundan bakan bir restoran sahibine hangisini
     * alacağını söylemez. Yayınlanmış her planın yanında, onun KENDİ
     * durumunu tarif eden bir cümle durmak zorunda.
     */
    public function test_every_published_plan_says_who_it_is_for(): void
    {
        $html = $this->html();
        $siteText = app(SiteText::class);

        foreach (DB::table('plans')->where('is_active', true)->get() as $plan) {
            $audience = $siteText->planAudienceLabel((string) $plan->code, 'en');

            self::assertNotNull(
                $audience,
                "PRICING-AUDIENCE-STATED-01: [{$plan->code}] yayında ama kime uygun olduğunu "
                .'söyleyen bir cümlesi yok. Ziyaretçi kademe adından kendi durumunu çıkaramaz.'
            );

            self::assertStringContainsString($audience, $html);
        }
    }

    // --- PRICING-AUDIENCE-FROM-CATALOG-01 ---------------------------------

    /**
     * Tanınmayan bir plan koduna kitle UYDURULMAZ.
     *
     * Sahip panelden yeni bir kademe açtığında sayfa ona hazır bir cümle
     * yakıştırmaz; hiç cümle çizmez. `entitlementLabel()` ile aynı sessizlik
     * kuralı — ve aynı bedeli: eşlemesi yazılmayan kademe sessizce cümlesiz
     * kalır, bu yüzden yukarıdaki kapı kataloğun HER kodunu ister.
     */
    public function test_a_plan_the_catalogue_does_not_know_gets_no_invented_audience(): void
    {
        self::assertNull(
            app(SiteText::class)->planAudienceLabel('enterprise-2027', 'en'),
            'PRICING-AUDIENCE-FROM-CATALOG-01: bilinmeyen bir kademeye kitle uydurulmamalı.'
        );
    }

    public function test_a_plan_added_from_the_panel_still_renders_without_an_audience_line(): void
    {
        DB::table('plans')->insert([
            'name' => 'Zincir',
            'code' => 'chain',
            'version' => 1,
            'is_active' => true,
            'sort_order' => 9,
            'entitlements' => json_encode([]),
            'amount_minor' => 249900,
            'currency' => 'TRY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $html = $this->html();

        // Plan GÖRÜNÜR — susan tek şey kitle cümlesi.
        self::assertStringContainsString('Zincir', $html);
        // Binlik ve ondalık ayıracı BİÇİMLENDİRİCİNİN işidir (`MoneyFormatter`)
        // ve dile göre değişir; burada ölçülen şey rakamın ve para biriminin
        // birlikte durduğudur.
        self::assertMatchesRegularExpression('#2[.,\x{00A0} ]?499[.,]00#u', $html);
        self::assertStringContainsString('TRY', $html);
    }

    // --- PRICING-EXCLUSIONS-STATED-01 -------------------------------------

    /**
     * NE DAHİL DEĞİL, açıkça — ve hiçbir planda.
     *
     * Bir fiyat sayfasının en pahalı sessizliği burasıdır. Altı satırın altısı
     * da ölçülmüş bir yokluk ve dili sipariş sayfasıyla (`OrderingPage`) ve
     * kurumsal fiyat sayfasıyla (`PricingPage`) ortak.
     */
    public function test_the_page_says_what_no_plan_includes(): void
    {
        $html = $this->html();

        self::assertMatchesRegularExpression(
            '#<ul[^>]*data-pricing-excluded#',
            $html,
            'PRICING-EXCLUSIONS-STATED-01: "ne dahil değil" bölümü hiç çizilmemiş.'
        );

        foreach ([
            // Masada ödeme yok (`OrderingPage`: "It does not take payment").
            'Guests do not pay through Zabuno',
            // POS/adisyon bağlantısı yok ("It does not talk to your till").
            'no connection to a till',
            // Paket servis yok ("Only from a table in the room").
            'no takeaway, no delivery',
            // Mutfak donanımı yok ("Nothing prints and nothing beeps").
            'Nothing prints and nothing beeps',
            // Kampanya yok (`PricingPage`: "No trial, no discount, no campaign").
            'no trial period',
            // Tek para birimi (`PricingPage`: "One currency").
            'Turkish lira only',
        ] as $absence) {
            self::assertStringContainsString(
                $absence,
                $html,
                "PRICING-EXCLUSIONS-STATED-01: ölçülmüş bir yokluk sayfada yazmıyor: {$absence}"
            );
        }
    }

    // --- PRICING-FAQ-ANSWERS-REAL-QUESTIONS-01 ----------------------------

    /**
     * SSS uydurulmadı: her sorunun karşılığı depoda ölçülebilir.
     *
     * Kaynaklar — yardım makalesi (basılı kod ölmez), ürünün "ne değildir"
     * listeleri (deneme süresi, şube/kişi başı fiyat) ve YAYINLANMIŞ yasal
     * metin (iptal, yürürlük, iade, plan değiştirme, ödemesiz süre).
     */
    public function test_the_faq_carries_the_questions_asked_before_paying(): void
    {
        $html = $this->html();

        self::assertSame(
            7,
            preg_match_all('#<details[^>]*data-pricing-faq#', $html),
            'PRICING-FAQ-ANSWERS-REAL-QUESTIONS-01: yedi soru bekleniyordu.'
        );

        foreach ([
            'What happens to my menu if I stop paying?',
            'Can I cancel it myself?',
            'Do I get money back if I cancel in the middle of a period?',
            'How do I move to a bigger or a smaller plan?',
            'Is the free plan a trial that runs out?',
            'Does a second branch or another waiter cost more?',
            'Do I have to reprint the codes if I change my prices or my plan?',
        ] as $question) {
            self::assertStringContainsString($question, $html);
        }

        /*
            CEVAP DA HTML'DE DURUR. `<details>` kapalı başlar ama içeriği
            gövdededir: arama motoru ve JavaScript çalıştırmayan bot için
            gövde budur (`docs/118` E2). Betikle sonradan doldurulsaydı,
            sayfanın en çok aranan yarısı hiçbir bota görünmezdi.
        */
        self::assertStringContainsString('Cancelling stops the renewal', $html);
        self::assertStringContainsString('Print once', $html);
    }

    // --- PRICING-EXIT-PATH-LINKED-01 --------------------------------------

    /**
     * ÖDEME VE ÇIKIŞ YOLU, fiyatın YANINDA.
     *
     * "Nasıl ödeyeceğim?" ve "çıkmak istersem ne olur?" soruları tam burada
     * sorulur; cevabı yalnız sözleşmenin içinde bırakmak, ikisini de ödeme
     * adımından sonra öğrenmek demekti. İki satır da yasal metnin yerine
     * geçmez, ONA GÖTÜRÜR — ve bağlantıların gerçekten açıldığı ölçülüyor.
     */
    public function test_the_page_links_the_payment_method_and_the_way_out(): void
    {
        $html = $this->html();

        self::assertMatchesRegularExpression('#data-payment-methods#', $html);
        self::assertMatchesRegularExpression('#data-cancellation#', $html);

        foreach (['/pre-information', '/refund-policy', '/contact'] as $target) {
            self::assertStringContainsString('href="'.$target.'"', $html);
            $this->get($target)->assertOk();
        }
    }

    // --- PRICING-NO-INVENTED-CLAIM-01 -------------------------------------

    /**
     * Bir fiyat sayfasının en kolay uydurduğu şeyler.
     *
     * Ölçüm: bunların hiçbiri depoda yok. "En popüler" rozeti ölçülmüş bir
     * popülerlik gerektirir ve böyle bir ölçüm hiç yapılmadı; indirim,
     * kampanya ve deneme süresi katalogda yok; müşteri sayısı ise sıfır.
     */
    public function test_the_page_invents_no_discount_no_badge_and_no_customer_count(): void
    {
        $html = $this->html();

        foreach ([
            '#most popular#i',
            '#best value#i',
            '#save \d+%#i',
            '#\d+% off#i',
            '#money[- ]back guarantee#i',
            '#free trial#i',
            '#\d[\d.,]* restaurants (already )?(use|trust)#i',
        ] as $invention) {
            self::assertDoesNotMatchRegularExpression(
                $invention,
                $html,
                "PRICING-NO-INVENTED-CLAIM-01: ölçülmemiş bir iddia sayfaya girmiş: {$invention}"
            );
        }
    }

    // --- PRICING-MOBILE-FIRST-01 ------------------------------------------

    /**
     * 320 PİKSEL TABAN.
     *
     * Fiyat tablosu dar ekranda en zor çizilen şeydir ve buradaki cevap
     * medya sorgusu değil, KART YIĞINI. Bu kapı kaynağı okur — düzenin
     * gerçekten ayakta kaldığını `scripts/mobile-ux-audit` gerçek bir
     * tarayıcıda ölçer; ikisi ayrı sorulardır ve biri ötekinin yerine
     * geçmez.
     */
    public function test_the_page_is_a_card_stack_and_carries_no_breakpoint_or_scroller(): void
    {
        $view = (string) file_get_contents(resource_path('views/public/pricing.blade.php'));

        self::assertDoesNotMatchRegularExpression(
            '/(^|[\s"])(sm|md|lg|xl|2xl):/m',
            $view,
            'PRICING-MOBILE-FIRST-01: kırılma noktası sınıfı (`MP-05`).'
        );
        self::assertDoesNotMatchRegularExpression(
            '/overflow-x|<table/i',
            $view,
            'PRICING-MOBILE-FIRST-01: yatay kaydırılan bir fiyat tablosu, ikinci sütununu '
            .'kimsenin görmediği bir tablodur.'
        );

        // Atlama bağlantısının hedefi. Bu sayfada YOKTU: `#main-content`
        // hiçbir yere gitmiyordu ve klavyeyle gezen biri kabuğu her seferinde
        // baştan geçiyordu.
        self::assertStringContainsString('id="main-content"', $this->html());
    }
}
