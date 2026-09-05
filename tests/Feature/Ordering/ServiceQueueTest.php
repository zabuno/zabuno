<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use App\Domain\Ordering\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Ordering\Concerns\BuildsOrderingFixture;
use Tests\TestCase;

/**
 * GARSON KUYRUĞU — `docs/115` §7 S4, hikâyeler G1–G5 (FF-179).
 *
 * Kuyruk bu ürünün tek insani kapısıdır: misafirin gönderdiği bir TALEP,
 * garsonun onayladığı bir İŞtir. Bu yüzden buradaki testler bir liste
 * ekranını değil, o kapının çalışıp çalışmadığını sınar.
 */
final class ServiceQueueTest extends TestCase
{
    use BuildsOrderingFixture;
    use RefreshDatabase;

    public function test_the_queue_lists_only_pending_orders_of_this_branch_oldest_first(): void
    {
        $shop = $this->orderingWorkspace('kuyruk-liste');

        $newer = $this->placeOrder($shop, minutesAgo: 1);
        $older = $this->placeOrder($shop, minutesAgo: 9);
        // Onaylanmış sipariş artık mutfağın işidir; garson kuyruğunda durursa
        // aynı sipariş iki kez onaylanmaya çalışılır.
        $this->placeOrder($shop, OrderStatus::Confirmed);

        $neighbour = $this->orderingWorkspace('kuyruk-komsu');
        $this->placeOrder($neighbour);

        $response = $this->actingAs($shop['user'])->getJson(
            "/api/workspaces/{$shop['workspaceId']}/locations/{$shop['locationId']}/orders/pending",
        );

        $response->assertOk();

        $ids = array_column($response->json('data'), 'id');

        // G1: en eski üstte. Servis anında sıra bir nezaket değil, misafirin
        // ne kadar beklediğinin tek kaydıdır.
        self::assertSame([$older, $newer], $ids);
    }

    public function test_a_queue_row_carries_the_table_the_lines_the_allergens_and_the_total(): void
    {
        $shop = $this->orderingWorkspace('kuyruk-satir');
        $orderId = $this->placeOrder($shop, productName: 'Fırın Sütlaç', allergens: ['milk', 'gluten']);

        $response = $this->actingAs($shop['user'])->getJson(
            "/api/workspaces/{$shop['workspaceId']}/locations/{$shop['locationId']}/orders/pending",
        );

        $response->assertOk();

        $row = $response->json('data.0');

        self::assertSame($orderId, $row['id']);
        // Masa adı garsonun siparişi NEREYE götüreceğidir; kimliği değil.
        self::assertSame('Masa 7', $row['tableName']);
        self::assertSame('Salon', $row['areaLabel']);
        self::assertSame(4250, $row['totalMinorAmount']);
        self::assertSame('TRY', $row['currencyCode']);
        self::assertSame('Fırın Sütlaç', $row['lines'][0]['productName']);
        // K4: alerjen SATIRDA görünür; ürüne gidip bakılmaz.
        self::assertSame(['milk', 'gluten'], $row['lines'][0]['allergens']);
        // Şubenin dilimi satırla birlikte gelir: "18:41" hangi şehrin 18:41'i.
        self::assertSame('Europe/Istanbul', $row['timeZone']);
    }

    public function test_the_feed_carries_the_server_moment_so_the_screen_does_not_trust_its_own_clock(): void
    {
        /*
            Mutfağa asılan ekranın saati yanlış olabilir ve genellikle
            yanlıştır. "9 dakikadır bekliyor" cümlesi o saatten hesaplanırsa,
            ekran kendi hatasını misafirin bekleme süresi diye gösterir.
            Sunucu kendi ANINI gönderir; ekran farkı bir kez ölçer.
        */
        $shop = $this->orderingWorkspace('kuyruk-saat');
        $this->placeOrder($shop);

        $response = $this->actingAs($shop['user'])->getJson(
            "/api/workspaces/{$shop['workspaceId']}/locations/{$shop['locationId']}/orders/pending",
        );

        $response->assertOk();
        self::assertNotNull($response->json('serverTime'));
    }

    public function test_confirming_moves_the_order_to_the_kitchen(): void
    {
        $shop = $this->orderingWorkspace('kuyruk-onay');
        $orderId = $this->placeOrder($shop);

        $this->actingAs($shop['user'])->putJson(
            "/api/workspaces/{$shop['workspaceId']}/locations/{$shop['locationId']}/orders/{$orderId}/status",
            ['status' => 'confirmed'],
        )->assertOk()->assertJsonPath('status', 'confirmed');

        self::assertNotNull(DB::table('orders')->where('id', $orderId)->value('confirmed_at'));
    }

    public function test_a_second_confirmation_is_refused_and_says_the_current_state(): void
    {
        $shop = $this->orderingWorkspace('kuyruk-cifte-onay');
        $orderId = $this->placeOrder($shop);
        $url = "/api/workspaces/{$shop['workspaceId']}/locations/{$shop['locationId']}/orders/{$orderId}/status";

        $this->actingAs($shop['user'])->putJson($url, ['status' => 'confirmed'])->assertOk();

        /*
            G5. İkinci garson "tamam" görmemeli — 409, çünkü ortada bir
            hata yok, bir ÇAKIŞMA var: iş zaten alınmış. Yanıt siparişin O
            ANKİ durumunu taşır ki ekran listeyi tazelemeden doğru cümleyi
            kurabilsin.
        */
        $this->actingAs($shop['user'])->putJson($url, ['status' => 'confirmed'])
            ->assertStatus(409)
            ->assertJsonPath('status', 'confirmed');
    }

    public function test_a_rejection_without_a_reason_is_refused(): void
    {
        $shop = $this->orderingWorkspace('kuyruk-sebepsiz');
        $orderId = $this->placeOrder($shop);

        // G3: sebep misafirin ekranında görünür. Sebepsiz ret ona yalnız
        // "olmadı" der ve neyi düzelteceğini bilmez.
        $this->actingAs($shop['user'])->putJson(
            "/api/workspaces/{$shop['workspaceId']}/locations/{$shop['locationId']}/orders/{$orderId}/status",
            ['status' => 'rejected'],
        )->assertStatus(422)->assertJsonValidationErrors('reason');

        self::assertSame(
            OrderStatus::Pending->value,
            (string) DB::table('orders')->where('id', $orderId)->value('status'),
        );
    }

    public function test_a_rejection_with_a_reason_stores_the_reason_for_the_guest_screen(): void
    {
        $shop = $this->orderingWorkspace('kuyruk-sebepli');
        $orderId = $this->placeOrder($shop);

        $this->actingAs($shop['user'])->putJson(
            "/api/workspaces/{$shop['workspaceId']}/locations/{$shop['locationId']}/orders/{$orderId}/status",
            ['status' => 'rejected', 'reason' => 'Bu masada kimse oturmuyor.'],
        )->assertOk();

        $order = DB::table('orders')->where('id', $orderId)->first();

        self::assertSame(OrderStatus::Rejected->value, (string) $order->status);
        self::assertSame('Bu masada kimse oturmuyor.', (string) $order->rejection_reason);
    }

    public function test_the_kitchen_role_can_see_the_service_queue_but_cannot_confirm(): void
    {
        $shop = $this->orderingWorkspace('kuyruk-mutfak-rolu', role: 'kitchen');
        $orderId = $this->placeOrder($shop);

        $this->actingAs($shop['user'])->getJson(
            "/api/workspaces/{$shop['workspaceId']}/locations/{$shop['locationId']}/orders/pending",
        )->assertOk();

        // Onay bir SERVİS kararıdır: masada kimin oturduğunu gören kişi verir.
        $this->actingAs($shop['user'])->putJson(
            "/api/workspaces/{$shop['workspaceId']}/locations/{$shop['locationId']}/orders/{$orderId}/status",
            ['status' => 'confirmed'],
        )->assertForbidden();
    }

    public function test_an_editor_never_learns_that_orders_exist(): void
    {
        $shop = $this->orderingWorkspace('kuyruk-editor', role: 'editor');
        $orderId = $this->placeOrder($shop);

        /*
            404, 403 DEĞİL. Editör sipariş yüzeyinin var olduğunu bile
            öğrenmemeli; 403 "burada bir şey var ama sana kapalı" der ve bu
            bir sayım kanalıdır. Bu depoda `order.view` yoksa yüzey yoktur.
        */
        $this->actingAs($shop['user'])->getJson(
            "/api/workspaces/{$shop['workspaceId']}/locations/{$shop['locationId']}/orders/pending",
        )->assertNotFound();

        $this->actingAs($shop['user'])->putJson(
            "/api/workspaces/{$shop['workspaceId']}/locations/{$shop['locationId']}/orders/{$orderId}/status",
            ['status' => 'confirmed'],
        )->assertNotFound();
    }

    public function test_a_neighbour_branch_cannot_move_this_order(): void
    {
        $shop = $this->orderingWorkspace('kuyruk-sinir');
        $orderId = $this->placeOrder($shop);

        $neighbour = $this->orderingWorkspace('kuyruk-sinir-komsu');

        // Şube sınırı SORGUNUN İÇİNDE: ekran bir düğmeyi gizleyebilir, istek
        // yine elle gönderilir.
        $this->actingAs($neighbour['user'])->putJson(
            "/api/workspaces/{$neighbour['workspaceId']}/locations/{$neighbour['locationId']}/orders/{$orderId}/status",
            ['status' => 'confirmed'],
        )->assertNotFound();

        self::assertSame(
            OrderStatus::Pending->value,
            (string) DB::table('orders')->where('id', $orderId)->value('status'),
        );
    }
}
