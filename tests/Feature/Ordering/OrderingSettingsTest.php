<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use App\Domain\Entitlement\Entitlement;
use App\Domain\Ordering\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Ordering\Concerns\BuildsOrderingFixture;
use Tests\Support\GrantsPlanEntitlements;
use Tests\TestCase;

/**
 * SİPARİŞ AYARLARI VE GEÇMİŞ — `docs/115` §7 S6, hikâyeler Y1–Y3 (FF-179/184).
 *
 * Şalter göçte VARSAYILAN KAPALI yazıldı ve bu paketin işi o kararı panele
 * taşımaktır: sahip açmadan hiçbir sipariş alınmaz. Kendiliğinden açılan bir
 * şalter, güncelledikten sonra hiçbir şey yapmayan bir restoranın mutfağına
 * sessizce iş düşürürdü.
 *
 * ═══ Y3: ŞALTER, PLANIN VERMEDİĞİ BİR SÖZÜ VEREMEZ ═══
 *
 * Ölçülen kusur şuydu: şalter panelden AÇILABİLİYORDU ama planda
 * `ordering.basic` yoksa misafirin siparişi 402 ile reddediliyordu. Yani
 * sahip hizmeti açtığını sanıyor, mutfağa hiçbir şey düşmüyor ve bunu ancak
 * bir misafir sipariş göndermeye çalıştığında — o da sessizce — öğreniyordu.
 * Bu dosyanın Y3 testleri o sessizliği kapatıyor: aynı hak, aynı ad, aynı
 * durum kodu, misafir tarafındaki kapının kullandığı dilin aynısı.
 */
final class OrderingSettingsTest extends TestCase
{
    use BuildsOrderingFixture;
    use GrantsPlanEntitlements;
    use RefreshDatabase;

    public function test_a_new_branch_does_not_accept_orders_until_the_owner_says_so(): void
    {
        $shop = $this->orderingWorkspace('ayar-varsayilan');

        $this->actingAs($shop['user'])->getJson(
            "/api/workspaces/{$shop['workspaceId']}/locations/{$shop['locationId']}/ordering",
        )->assertOk()->assertJsonPath('acceptsOrders', false);
    }

    public function test_the_owner_turns_ordering_on_and_off(): void
    {
        $shop = $this->orderingWorkspace('ayar-salter');
        // Y3: şalteri AÇMAK artık planın verdiği bir hak ister. Hak burada
        // açıkça kuruluyor ki bu testin sınadığı şey yine yalnız şalterin
        // kendisi olsun — plan kapısının kendi testi aşağıda.
        $this->grantEntitlements($shop['workspaceId'], [Entitlement::OrderingBasic]);
        $url = "/api/workspaces/{$shop['workspaceId']}/locations/{$shop['locationId']}/ordering";

        $this->actingAs($shop['user'])->putJson($url, ['acceptsOrders' => true])
            ->assertOk()->assertJsonPath('acceptsOrders', true);

        self::assertSame(1, (int) DB::table('locations')->where('id', $shop['locationId'])->value('accepts_orders'));

        $this->actingAs($shop['user'])->putJson($url, ['acceptsOrders' => false])
            ->assertOk()->assertJsonPath('acceptsOrders', false);

        self::assertSame(0, (int) DB::table('locations')->where('id', $shop['locationId'])->value('accepts_orders'));
    }

    public function test_a_manager_may_read_the_switch_but_not_flip_it(): void
    {
        $shop = $this->orderingWorkspace('ayar-yonetici', role: 'manager');
        // Hak VERİLİYOR: bu testin cevabı "rolün yetmiyor" olmalı, "planın
        // yetmiyor" değil. İkisi aynı anda eksik olsaydı test hangi kapının
        // kapattığını söylemezdi.
        $this->grantEntitlements($shop['workspaceId'], [Entitlement::OrderingBasic]);
        $url = "/api/workspaces/{$shop['workspaceId']}/locations/{$shop['locationId']}/ordering";

        // Yönetici servisi yürütür — kuyruğu görür, onaylar. Hizmeti açıp
        // kapatmak bir işletme kararıdır ve sahibinde kalır (`docs/115` §4).
        $this->actingAs($shop['user'])->getJson($url)->assertOk();
        $this->actingAs($shop['user'])->putJson($url, ['acceptsOrders' => true])->assertForbidden();

        self::assertSame(0, (int) DB::table('locations')->where('id', $shop['locationId'])->value('accepts_orders'));
    }

    /**
     * Y3 — ŞALTER, PLANIN VERMEDİĞİ HİZMETİ AÇAMAZ.
     *
     * Ölçülen sonuç: şalter açılıyordu, misafirin siparişi 402 ile
     * reddediliyordu ve sahip aradaki farkı hiçbir yerde göremiyordu. Bu
     * test SONUCU ölçüyor: sütun kapalı KALIR. Sunucuda kapatmak şart,
     * çünkü yalnız ekranı kilitlemek kuralı bir cümleye indirger — uç açık
     * kaldığı sürece kural, isteği elle gönderen ilk kişide biter.
     */
    public function test_the_switch_cannot_open_a_service_the_plan_does_not_include(): void
    {
        $shop = $this->orderingWorkspace('ayar-plan-yok');
        $url = "/api/workspaces/{$shop['workspaceId']}/locations/{$shop['locationId']}/ordering";

        // Misafir tarafındaki kapı ile AYNI DİL: 402 ve hakkın adı. Sahip
        // panelde ne arayacağını, misafir ekranında ne yazdığını okuyan
        // kişiyle aynı kelimeyle öğrenir.
        $this->actingAs($shop['user'])->putJson($url, ['acceptsOrders' => true])
            ->assertStatus(402)
            ->assertJsonPath('entitlement', Entitlement::OrderingBasic->value);

        self::assertSame(0, (int) DB::table('locations')->where('id', $shop['locationId'])->value('accepts_orders'));
    }

    /**
     * Y3 — EKRAN, ŞALTERİ ÇİZMEDEN ÖNCE PLANI BİLİR.
     *
     * Okuma ucu "açık mı" ile "planda var mı" sorularını AYRI cevaplar.
     * Tek bir birleşik değer dönseydi, planı olmayan bir sahibin ekranı
     * kapalı bir şalter gösterir ve sebebini söyleyemezdi — yani sahip onu
     * açmayı deneyip 402'ye çarparak öğrenirdi.
     */
    public function test_the_screen_is_told_whether_the_plan_includes_ordering(): void
    {
        $shop = $this->orderingWorkspace('ayar-plan-okuma');
        $url = "/api/workspaces/{$shop['workspaceId']}/locations/{$shop['locationId']}/ordering";

        $this->actingAs($shop['user'])->getJson($url)
            ->assertOk()
            ->assertJsonPath('planIncludesOrdering', false)
            ->assertJsonPath('entitlement', Entitlement::OrderingBasic->value);

        $this->grantEntitlements($shop['workspaceId'], [Entitlement::OrderingBasic]);

        $this->actingAs($shop['user'])->getJson($url)
            ->assertOk()
            ->assertJsonPath('planIncludesOrdering', true);
    }

    /**
     * Y3 — HAK DÜŞTÜĞÜNDE ŞALTER SESSİZCE KAPATILMAZ.
     *
     * Abonelik biterse şalter AÇIK kalmış olabilir ve ekran "açık ama
     * çalışmıyor" gerçeğini söylemek zorundadır. Sunucunun sahibin ayarını
     * arkasından değiştirmesi daha temiz görünürdü ve daha kötü olurdu:
     * sahip planını geri aldığında neyi kaybettiğini bilmez, sipariş
     * almadığını fark etmesi için bir akşamın geçmesi gerekirdi.
     *
     * Kapatmak ise HER ZAMAN serbest: sahibi kendi hizmetini kapatamadığı
     * bir ekranda bırakmak, planı düşmüş bir restoranı kilitlemek olurdu.
     */
    public function test_a_lapsed_plan_leaves_the_switch_where_the_owner_left_it(): void
    {
        $shop = $this->orderingWorkspace('ayar-plan-dustu');
        $url = "/api/workspaces/{$shop['workspaceId']}/locations/{$shop['locationId']}/ordering";

        $this->grantEntitlements($shop['workspaceId'], [Entitlement::OrderingBasic]);
        $this->actingAs($shop['user'])->putJson($url, ['acceptsOrders' => true])->assertOk();

        DB::table('subscriptions')->where('workspace_id', $shop['workspaceId'])->delete();

        $this->actingAs($shop['user'])->getJson($url)
            ->assertOk()
            ->assertJsonPath('acceptsOrders', true)
            ->assertJsonPath('planIncludesOrdering', false);

        self::assertSame(1, (int) DB::table('locations')->where('id', $shop['locationId'])->value('accepts_orders'));

        $this->actingAs($shop['user'])->putJson($url, ['acceptsOrders' => false])->assertOk();

        self::assertSame(0, (int) DB::table('locations')->where('id', $shop['locationId'])->value('accepts_orders'));
    }

    public function test_a_neighbour_workspace_cannot_flip_this_branch_switch(): void
    {
        $shop = $this->orderingWorkspace('ayar-sinir');
        $neighbour = $this->orderingWorkspace('ayar-sinir-komsu');
        // Komşunun planı TAM: cevabın 404 olması "senin planında yok"tan
        // değil, o şubenin ona ait olmamasından gelmeli. Var olmayan bir
        // şubenin planını konuşmak, komşunun şubesini tanımak olurdu.
        $this->grantEntitlements($neighbour['workspaceId'], [Entitlement::OrderingBasic]);

        $this->actingAs($neighbour['user'])->putJson(
            "/api/workspaces/{$neighbour['workspaceId']}/locations/{$shop['locationId']}/ordering",
            ['acceptsOrders' => true],
        )->assertNotFound();

        self::assertSame(0, (int) DB::table('locations')->where('id', $shop['locationId'])->value('accepts_orders'));
    }

    public function test_history_keeps_closed_orders_and_carries_the_rejection_reason(): void
    {
        $shop = $this->orderingWorkspace('ayar-gecmis');

        $delivered = $this->placeOrder($shop, OrderStatus::Delivered, minutesAgo: 30);
        $rejected = $this->placeOrder($shop, OrderStatus::Rejected, minutesAgo: 20);
        $pending = $this->placeOrder($shop, OrderStatus::Pending, minutesAgo: 10);

        DB::table('orders')->where('id', $rejected)->update(['rejection_reason' => 'Masa boştu.']);

        $response = $this->actingAs($shop['user'])->getJson(
            "/api/workspaces/{$shop['workspaceId']}/locations/{$shop['locationId']}/orders/history",
        );

        $response->assertOk();

        /*
            Y2: geçmiş SİLİNMEZ ve eksiltilmez. Açık siparişler de listede,
            çünkü "bugün ne oldu" sorusunun cevabı yalnız kapananlardan
            oluşmaz. En YENİ üstte: geçmişte aranan şey son olandır.
        */
        self::assertSame([$pending, $rejected, $delivered], array_column($response->json('data'), 'id'));
        self::assertSame('Masa boştu.', $response->json('data.1.rejectionReason'));
    }

    public function test_history_is_scoped_to_the_branch(): void
    {
        $shop = $this->orderingWorkspace('ayar-gecmis-sinir');
        $this->placeOrder($shop, OrderStatus::Delivered);

        $neighbour = $this->orderingWorkspace('ayar-gecmis-komsu');

        $response = $this->actingAs($neighbour['user'])->getJson(
            "/api/workspaces/{$neighbour['workspaceId']}/locations/{$neighbour['locationId']}/orders/history",
        );

        $response->assertOk();
        self::assertSame([], $response->json('data'));
    }

    public function test_an_editor_cannot_read_the_history(): void
    {
        $shop = $this->orderingWorkspace('ayar-gecmis-editor', role: 'editor');
        $this->placeOrder($shop, OrderStatus::Delivered);

        $this->actingAs($shop['user'])->getJson(
            "/api/workspaces/{$shop['workspaceId']}/locations/{$shop['locationId']}/orders/history",
        )->assertNotFound();
    }
}
