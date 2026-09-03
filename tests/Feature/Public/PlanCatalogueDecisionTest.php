<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Domain\Entitlement\Entitlement;
use Database\Seeders\PlanCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FF-32 RED — yayımlanmış plan kataloğu (`docs/90`).
 *
 * `docs/88` fiyat sayfasını kurdu ama katalog boştu, dolayısıyla sayfa
 * "fiyatlar henüz yayımlanmadı" diyordu. Sahip kararı bana bıraktı.
 *
 * KADEMELER UYDURULMAZ, UYGULANANDAN TÜRETİLİR. Bu üründe tam üç yetenek
 * plana bağlı: toplu QR, ekip daveti, analitik. Temel zincir
 * (kayıt → menü → yayın → QR) PLANSIZ çalışır ve bir test bunu dondurur.
 * Uygulanmayan bir kademe satmak, parası alınan ama kapanmayan bir kapı
 * demektir.
 *
 * Requirement IDs: PLAN-CATALOG-PUBLISHED-01, PLAN-TIERS-MATCH-ENFORCED-01,
 * PLAN-FREE-IS-FREE-01, PLAN-INCLUDED-STATED-ONCE-01,
 * PLAN-LABELS-ARE-HUMAN-01.
 */
final class PlanCatalogueDecisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /*
            Tohum AÇIKÇA çağrılır.

            İlk hâlinde bunu bir göçe yazmıştım ve dokuz mevcut test kırıldı:
            hepsi `plans` tablosunun boş başladığını varsayıyordu ve biri tam
            olarak BOŞ tablo davranışını ölçüyordu. Testler haklıydı — göç
            ŞEMA içindir, iş verisi için değil (`docs/90`).
        */
        $this->seed(PlanCatalogueSeeder::class);
    }

    // --- PLAN-CATALOG-PUBLISHED-01 ----------------------------------------

    public function test_the_catalogue_ships_with_published_plans(): void
    {
        $plans = DB::table('plans')->where('is_active', true)->orderBy('sort_order')->get();

        self::assertCount(3, $plans, 'PLAN-CATALOG-PUBLISHED-01: üç kademe yayımlanmalı.');

        self::assertSame(
            ['starter', 'restaurant', 'team'],
            $plans->pluck('code')->all(),
            'Sıra sahibin sunmak istediği sıradır: ücretsizden yukarı.'
        );
    }

    // --- PLAN-TIERS-MATCH-ENFORCED-01 -------------------------------------

    public function test_every_sold_capability_is_one_the_product_actually_gates(): void
    {
        $enforced = array_map(
            static fn (Entitlement $e): string => $e->value,
            Entitlement::cases(),
        );

        foreach (DB::table('plans')->get() as $plan) {
            foreach ((array) json_decode((string) $plan->entitlements, true) as $key) {
                self::assertContains(
                    $key,
                    $enforced,
                    "PLAN-TIERS-MATCH-ENFORCED-01: [{$plan->code}] uygulanmayan bir yetenek satıyor: {$key}. "
                    .'Parası alınan ama kapanmayan bir kapı, en pahalı yalandır.'
                );
            }
        }
    }

    public function test_the_ladder_only_grows(): void
    {
        $plans = DB::table('plans')->where('is_active', true)->orderBy('sort_order')->get();

        $previous = [];

        foreach ($plans as $plan) {
            $current = (array) json_decode((string) $plan->entitlements, true);

            // Üst kademe alt kademenin HER ŞEYİNİ içerir. İçermeseydi
            // "yükselt" düğmesi bazı şeyleri kaybettirirdi.
            foreach ($previous as $inherited) {
                self::assertContains(
                    $inherited,
                    $current,
                    "[{$plan->code}] bir alt kademedeki [{$inherited}] yeteneğini kaybediyor."
                );
            }

            $previous = $current;
        }
    }

    // --- PLAN-FREE-IS-FREE-01 ---------------------------------------------

    public function test_the_free_tier_reads_as_free_not_as_zero_lira(): void
    {
        $html = $this->get('/pricing')->getContent();

        // `0,00 TRY` teknik olarak doğru ama insan onu "ücretsiz" diye
        // okumaz — bir hata sanır.
        self::assertMatchesRegularExpression('#Free#', $html);
        self::assertDoesNotMatchRegularExpression('#0[.,]00#', $html);
    }

    // --- PLAN-INCLUDED-STATED-ONCE-01 -------------------------------------

    public function test_the_page_says_once_what_every_plan_includes(): void
    {
        $html = $this->get('/pricing')->getContent();

        /*
            Yetenek listesi EK yetkileri anlatır; temel zinciri değil. Yalnız
            onları göstermek, ücretsiz kademeyi "hiçbir şey içermiyor" gibi
            gösterirdi — oysa menü, yayın, karekod ve misafir sayfası her
            planda var.
        */
        self::assertMatchesRegularExpression('#[Ee]very plan includes#', $html);
        self::assertMatchesRegularExpression('#QR#', $html);
    }

    // --- PLAN-LABELS-ARE-HUMAN-01 -----------------------------------------

    public function test_a_visitor_never_reads_our_internal_keys(): void
    {
        $html = $this->get('/pricing')->getContent();

        foreach (Entitlement::cases() as $entitlement) {
            self::assertStringNotContainsString(
                $entitlement->value,
                $html,
                "PLAN-LABELS-ARE-HUMAN-01: `{$entitlement->value}` geliştirici dilidir; "
                .'müşteri sayfasında görünmemeli.'
            );
        }

        // Yerine insanca karşılıkları görünür.
        self::assertMatchesRegularExpression('#Bulk QR#i', $html);
        self::assertMatchesRegularExpression('#[Tt]eam#', $html);
        self::assertMatchesRegularExpression('#[Aa]nalytics#', $html);
    }
}
