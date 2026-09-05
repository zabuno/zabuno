<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use App\Domain\Ordering\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Ordering\Concerns\BuildsOrderingFixture;
use Tests\TestCase;

/**
 * SİPARİŞ AYARLARI VE GEÇMİŞ — `docs/115` §7 S6, hikâyeler Y1–Y2 (FF-179).
 *
 * Şalter göçte VARSAYILAN KAPALI yazıldı ve bu paketin işi o kararı panele
 * taşımaktır: sahip açmadan hiçbir sipariş alınmaz. Kendiliğinden açılan bir
 * şalter, güncelledikten sonra hiçbir şey yapmayan bir restoranın mutfağına
 * sessizce iş düşürürdü.
 */
final class OrderingSettingsTest extends TestCase
{
    use BuildsOrderingFixture;
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
        $url = "/api/workspaces/{$shop['workspaceId']}/locations/{$shop['locationId']}/ordering";

        // Yönetici servisi yürütür — kuyruğu görür, onaylar. Hizmeti açıp
        // kapatmak bir işletme kararıdır ve sahibinde kalır (`docs/115` §4).
        $this->actingAs($shop['user'])->getJson($url)->assertOk();
        $this->actingAs($shop['user'])->putJson($url, ['acceptsOrders' => true])->assertForbidden();

        self::assertSame(0, (int) DB::table('locations')->where('id', $shop['locationId'])->value('accepts_orders'));
    }

    public function test_a_neighbour_workspace_cannot_flip_this_branch_switch(): void
    {
        $shop = $this->orderingWorkspace('ayar-sinir');
        $neighbour = $this->orderingWorkspace('ayar-sinir-komsu');

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
