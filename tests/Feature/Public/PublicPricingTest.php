<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Application\Billing\Port\PlanCatalogRepositoryPort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * P1-01 RED — kaydolmadan görülebilen fiyat (`docs/88`).
 *
 * MÜŞTERİ SORUNU. Siteye gelen bir restoran sahibi üç soru sorar: ne kadar,
 * deneyebilir miyim, tıkanırsam kime sorarım. Fiyat sorusunun cevabı sayfada
 * "henüz yok" yazıyordu — ve plan listesi `auth` + çalışma alanı bağlamı
 * ardındaydı, yani **kaydolmadan görülemiyordu**. Fiyatı görmek için
 * kaydolmak gereken bir ürün, kaydolmayı fiyatı görmeye bağlı kılıyor.
 *
 * FİYAT VERİDİR, KOD DEĞİL. Sayfa plan kataloğundan okur; rakamı sahibi
 * girer. Sayfaya elle yazmak, fiyat değişince ikinci bir gerçek kaynak
 * yaratırdı.
 *
 * Requirement IDs: PUBLIC-PRICING-NO-AUTH-01, PUBLIC-PRICING-FROM-CATALOG-01,
 * PUBLIC-PRICING-EMPTY-HONEST-01, PUBLIC-PRICING-INACTIVE-HIDDEN-01.
 */
final class PublicPricingTest extends TestCase
{
    use RefreshDatabase;

    private function plan(string $code, string $name, ?int $amountMinor, ?string $currency, int $sort = 0, bool $active = true): void
    {
        DB::table('plans')->insert([
            'name' => $name,
            'code' => $code,
            'version' => 1,
            'is_active' => $active,
            'sort_order' => $sort,
            'entitlements' => json_encode(['menu.publish', 'qr.create']),
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // --- PUBLIC-PRICING-NO-AUTH-01 / FROM-CATALOG-01 ----------------------

    public function test_a_visitor_who_never_signed_up_sees_the_real_prices(): void
    {
        $this->plan('starter', 'Başlangıç', 29900, 'TRY', 1);
        $this->plan('growth', 'Büyüme', 59900, 'TRY', 2);

        // OTURUM YOK: fiyatı görmek için kaydolmak gerekmiyor.
        $response = $this->get('/pricing');

        $response->assertOk();

        $html = $response->getContent();

        self::assertStringContainsString('Başlangıç', $html);
        self::assertStringContainsString('Büyüme', $html);

        // Tutar ve PARA BİRİMİ birlikte: para birimsiz bir sayı, ziyaretçiye
        // hangi parayı ödeyeceğini söylemez.
        self::assertMatchesRegularExpression('#299[.,]00\s*TRY|TRY\s*299[.,]00#u', $html);
        self::assertMatchesRegularExpression('#599[.,]00\s*TRY|TRY\s*599[.,]00#u', $html);

        // Sıra kataloğun `sort_order` alanından gelir: sahibin planları
        // sunmak istediği sıra, veritabanı kimliğinin sırası değil.
        self::assertLessThan(
            strpos($html, 'Büyüme'),
            strpos($html, 'Başlangıç'),
        );
    }

    public function test_the_home_page_stops_saying_pricing_does_not_exist(): void
    {
        $this->plan('starter', 'Başlangıç', 29900, 'TRY');

        $home = $this->get('/')->getContent();

        self::assertStringContainsString('Başlangıç', $home);
        self::assertStringNotContainsString('There are no published plan prices yet', $home);

        // Ve SSS artık "henüz yok" demiyor.
        self::assertStringNotContainsString('Not yet — see the Pricing section above.', $home);
    }

    // --- PUBLIC-PRICING-INACTIVE-HIDDEN-01 --------------------------------

    public function test_a_retired_plan_is_not_offered_to_new_visitors(): void
    {
        $this->plan('starter', 'Başlangıç', 29900, 'TRY', 1);
        $this->plan('legacy', 'Eski Paket', 9900, 'TRY', 2, false);

        $html = $this->get('/pricing')->getContent();

        self::assertStringContainsString('Başlangıç', $html);
        self::assertStringNotContainsString(
            'Eski Paket',
            $html,
            'PUBLIC-PRICING-INACTIVE-HIDDEN-01: kaldırılmış bir plan yeni müşteriye sunulmamalı.'
        );
    }

    // --- PUBLIC-PRICING-EMPTY-HONEST-01 -----------------------------------

    public function test_with_no_published_plan_the_page_says_so_and_offers_a_way_forward(): void
    {
        $response = $this->get('/pricing');

        $response->assertOk();

        $html = $response->getContent();

        /*
            Boş bir fiyat tablosu, ziyaretçiye "bu ürün hazır değil"
            dedirtir. Sayfa DURUMU söyler ve bir çıkış yolu bırakır —
            `docs/66` disiplini: boş, bir hata değildir ama bir çıkmaz da
            olmamalıdır.
        */
        self::assertMatchesRegularExpression('#[Cc]ontact#', $html);
        self::assertStringContainsString('/contact', $html);
    }

    public function test_a_plan_without_a_price_is_shown_as_talk_to_us_not_as_free(): void
    {
        // Tutarı GİRİLMEMİŞ bir plan `amount_minor = null` taşır. Onu "0" ya
        // da "ücretsiz" göstermek, tutulmayacak bir söz vermek olurdu.
        $this->plan('enterprise', 'Kurumsal', null, null);

        $html = $this->get('/pricing')->getContent();

        self::assertStringContainsString('Kurumsal', $html);
        self::assertDoesNotMatchRegularExpression('#Kurumsal.{0,200}(free|Free|0[.,]00)#su', $html);
    }

    // --- PUBLIC-PRICING-SURVIVES-CATALOG-FAILURE-01 -----------------------

    public function test_the_marketing_site_does_not_die_when_the_catalogue_cannot_be_read(): void
    {
        /*
            Bu sayfalar bugüne kadar tamamen STATİKTİ; fiyatı katalogdan
            okumak onlara bir veritabanı bağımlılığı ekledi.

            Veritabanı bir an tökezlediğinde tanıtım sitesinin tamamının 500
            vermesi, fiyat göstermemekten çok daha kötü olurdu: ziyaretçi
            ürünün çöktüğünü görür ve bir daha gelmez.
        */
        $this->app->bind(PlanCatalogRepositoryPort::class, function (): PlanCatalogRepositoryPort {
            return new class implements PlanCatalogRepositoryPort
            {
                public function listActivePlans(): array
                {
                    throw new RuntimeException('katalog okunamıyor');
                }
            };
        });

        $response = $this->get('/pricing');

        $response->assertOk();

        // Ve dürüst boş hâle düşer — bir çıkmaz değil, bir çıkış yolu.
        self::assertStringContainsString('/contact', $response->getContent());
    }
}
