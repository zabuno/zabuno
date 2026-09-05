<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Domain\Entitlement\Entitlement;
use App\Support\Localization\SiteText;
use Database\Seeders\PlanCatalogueSeeder;
use Tests\TestCase;

/**
 * ÇALIŞAN AMA SATILAMAYAN YETENEK — `docs/122` §2, Y1.
 *
 * `PlanCatalogueDecisionTest` bir yönü zaten donduruyor: satılan her yetenek
 * ürünün gerçekten kapattığı bir yetenek olmalı ("parası alınan ama kapanmayan
 * kapı"). Ters yön ölçülmemişti ve tam orada bir kusur büyüdü.
 *
 * Sipariş hattı uçtan uca çalışıyor — misafir sepetten gönderiyor, sahip
 * onaylıyor, mutfak monitörde görüyor, hepsi testli — ama `ordering.basic`
 * hiçbir plana konmamıştı. Yani hiçbir restoran onu SATIN ALAMIYORDU: kod
 * yazıldı, para hattına hiç bağlanmadı. Bu kusur sessizdir, çünkü hiçbir şey
 * kırılmaz; yalnız bir gün "neden kimse sipariş özelliğini kullanmıyor?"
 * sorusuna yanlış cevap verdirir.
 *
 * Bu kapı o kusur ailesini kapatır: enum'a eklenen bir yetenek, bir kademeye
 * bağlanmadan yaşayamaz.
 *
 * Requirement IDs: PLAN-EVERY-CAPABILITY-SELLABLE-01,
 * PLAN-ORDERING-TIER-01, PLAN-RICH-MEDIA-TIER-01,
 * PLAN-LIVE-CAPABILITY-NAMED-01, PLAN-UNBUILT-NOT-ADVERTISED-01.
 */
final class PlanCatalogueSellsEveryCapabilityTest extends TestCase
{
    /** @return list<string> */
    private function soldKeys(): array
    {
        $sold = [];

        foreach (PlanCatalogueSeeder::catalogue() as $plan) {
            foreach ($plan['entitlements'] as $key) {
                $sold[] = $key;
            }
        }

        return array_values(array_unique($sold));
    }

    // --- PLAN-EVERY-CAPABILITY-SELLABLE-01 --------------------------------

    public function test_every_capability_the_product_gates_is_sold_in_at_least_one_plan(): void
    {
        $sold = $this->soldKeys();

        foreach (Entitlement::cases() as $capability) {
            self::assertContains(
                $capability->value,
                $sold,
                "PLAN-EVERY-CAPABILITY-SELLABLE-01: `{$capability->value}` bir kapı kapatıyor "
                .'ama hiçbir kademede satılmıyor. Çalışan ve satılamayan bir yetenek, hiç '
                .'yazılmamış bir yetenekten pahalıdır: bakımı ödenir, geliri gelmez.'
            );
        }
    }

    // --- PLAN-ORDERING-TIER-01 --------------------------------------------

    /**
     * Masadan sipariş ÜCRETLİ kademede başlar, en üstte değil.
     *
     * Ölçüm: sipariş akışı SAHİBİN TEK BAŞINA hesabıyla yürür — `docs/115` §4
     * `order.view`, `order.confirm` ve `order.kitchen` izinlerinin üçünü de
     * Sahip'e veriyor. Yani sipariş almak için ekip daveti gerekmiyor.
     * `team` kademesine koymak, tam da bu ürünün tarif ettiği kırk masalık
     * sahip-işletmeli restorana çalışan bir yeteneği satılamaz kılardı.
     *
     * `starter`'a konmaması da bir ölçüm: ücretsiz kademe misafire menüyü
     * gösterir ve sunucuya hiçbir sipariş satırı yazmaz; sipariş ise kalıcı
     * kayıt, mutfak yoklaması ve denetim izi üretir.
     */
    public function test_table_ordering_is_sold_from_the_first_paid_tier_up(): void
    {
        $catalogue = PlanCatalogueSeeder::catalogue();
        $key = Entitlement::OrderingBasic->value;

        self::assertNotContains($key, $catalogue['starter']['entitlements'], 'PLAN-ORDERING-TIER-01');
        self::assertContains($key, $catalogue['restaurant']['entitlements'], 'PLAN-ORDERING-TIER-01');
        self::assertContains($key, $catalogue['team']['entitlements'], 'PLAN-ORDERING-TIER-01');
    }

    // --- PLAN-RICH-MEDIA-TIER-01 ------------------------------------------

    /**
     * Zengin görsel de ilk ücretli kademede başlar, ve kademe UYDURULMADI:
     * `config/media-quota.php` ücretsiz kademeye 200 MB, `restaurant`
     * kademesine 2 GB veriyor. Zengin görseli 200 MB'lık bir kotanın üstünde
     * satmak, kotanın ilk günde bozacağı bir söz vermek olurdu.
     */
    public function test_rich_media_is_sold_from_the_first_paid_tier_up(): void
    {
        $catalogue = PlanCatalogueSeeder::catalogue();
        $key = Entitlement::MenuRichMedia->value;

        self::assertNotContains($key, $catalogue['starter']['entitlements'], 'PLAN-RICH-MEDIA-TIER-01');
        self::assertContains($key, $catalogue['restaurant']['entitlements'], 'PLAN-RICH-MEDIA-TIER-01');
        self::assertContains($key, $catalogue['team']['entitlements'], 'PLAN-RICH-MEDIA-TIER-01');
    }

    // --- PLAN-LIVE-CAPABILITY-NAMED-01 ------------------------------------

    /**
     * Satılan ve BUGÜN ÇALIŞAN bir yeteneğin ziyaretçi dilinde bir karşılığı
     * olmalı. `SiteText::entitlementLabel()` tanımadığı anahtarı hiç
     * göstermez (ham anahtar sızdırmamak için doğru bir davranış) — ama bu
     * doğru davranış, eşleme unutulduğunda yeteneği fiyat sayfasından sessizce
     * siler. `branding.custom` tam olarak böyle kayboldu: planda satılıyordu,
     * fiyat sayfasında hiç yazmıyordu.
     */
    public function test_a_capability_that_already_works_is_named_in_the_visitor_language(): void
    {
        $siteText = app(SiteText::class);

        $live = [
            Entitlement::QrBulkGeneration,
            Entitlement::AnalyticsReporting,
            Entitlement::TeamInvitations,
            Entitlement::BrandingCustom,
            Entitlement::OrderingBasic,
        ];

        foreach ($live as $capability) {
            $label = $siteText->entitlementLabel($capability->value, 'en');

            self::assertNotNull(
                $label,
                "PLAN-LIVE-CAPABILITY-NAMED-01: `{$capability->value}` satılıyor ve çalışıyor, "
                .'ama fiyat sayfasında insanca bir karşılığı yok; sayfa onu hiç göstermez. '
                .'Müşteri ödediği şeyi okuyamaz.'
            );
            self::assertNotSame($capability->value, $label, 'Ham anahtar müşteri dili değildir.');
        }
    }

    // --- PLAN-UNBUILT-NOT-ADVERTISED-01 -----------------------------------

    /**
     * ZENGİN GÖRSEL HENÜZ REKLAM EDİLMEZ.
     *
     * Hak burada tanımlıdır ve kademesi karara bağlanmıştır — böylece
     * `docs/114` Dalga 6 (`docs/122` Y6) yazıldığında bağlanacağı kapı hazır
     * olur ve o paket bir fiyat kararı vermek zorunda kalmaz. Ama misafir
     * yüzeyi HENÜZ YOK: bugün fiyat sayfasına "zengin görsel" yazmak,
     * ziyaretçiye ödemeden önce olmayan bir şey satmak olurdu.
     *
     * BU SATIRIN ÖMRÜ VAR (`docs/109` §8.6): Dalga 6 misafir yüzeyini
     * yazdığında eşleme eklenir ve bu iddia kırılır. Kırıldığında SİLİNİR —
     * çünkü o gün gerekçesi düşmüş olur.
     */
    public function test_rich_media_is_not_advertised_before_wave_six_builds_its_surface(): void
    {
        self::assertNull(
            app(SiteText::class)->entitlementLabel(Entitlement::MenuRichMedia->value, 'en'),
            'PLAN-UNBUILT-NOT-ADVERTISED-01: misafir yüzeyi yazılmadan zengin görsel '
            .'fiyat sayfasında duyurulmaz.'
        );
    }
}
