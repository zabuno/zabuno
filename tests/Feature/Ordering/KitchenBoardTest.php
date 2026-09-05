<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use App\Domain\Ordering\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Ordering\Concerns\BuildsOrderingFixture;
use Tests\TestCase;

/**
 * MUTFAK MONİTÖRÜ — `docs/115` §7 S5, hikâyeler K1–K5 (FF-179).
 *
 * Sahibin cümlesi: "Restoran Admin panelden onaylarsa sipariş mutfak
 * tarafındaki monitöre düşer." Buradaki testlerin tamamı o "ONAYLARSA"
 * kelimesinin bekçisidir: bekleyen bir sipariş monitöre DÜŞMEZ.
 */
final class KitchenBoardTest extends TestCase
{
    use BuildsOrderingFixture;
    use RefreshDatabase;

    public function test_the_monitor_shows_only_what_the_kitchen_may_see(): void
    {
        $shop = $this->orderingWorkspace('mutfak-gorunur');

        // Bekleyen mutfağa HİÇ görünmez: görünseydi aşçı onaylanmamış bir işi
        // hazırlamaya başlardı ve garsonun gözü diye bir kapı kalmazdı.
        $this->placeOrder($shop, OrderStatus::Pending);
        $confirmed = $this->placeOrder($shop, OrderStatus::Confirmed, minutesAgo: 5);
        $preparing = $this->placeOrder($shop, OrderStatus::Preparing, minutesAgo: 3);
        $ready = $this->placeOrder($shop, OrderStatus::Ready, minutesAgo: 1);
        // Kapanmışlar da düşer: teslim edilen bir fiş ekranda yer kaplarsa,
        // aşçı gerçekten bekleyeni bulmak için okumak zorunda kalır.
        $this->placeOrder($shop, OrderStatus::Delivered);
        $this->placeOrder($shop, OrderStatus::Rejected);
        $this->placeOrder($shop, OrderStatus::Cancelled);

        $response = $this->actingAs($shop['user'])->getJson(
            "/api/workspaces/{$shop['workspaceId']}/locations/{$shop['locationId']}/orders/kitchen",
        );

        $response->assertOk();

        self::assertSame(
            [$confirmed, $preparing, $ready],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_every_row_on_the_monitor_carries_its_allergen_copy(): void
    {
        $shop = $this->orderingWorkspace('mutfak-alerjen');
        $this->placeOrder($shop, OrderStatus::Confirmed, 'Mercimek Çorbası', ['celery']);

        $response = $this->actingAs($shop['user'])->getJson(
            "/api/workspaces/{$shop['workspaceId']}/locations/{$shop['locationId']}/orders/kitchen",
        );

        // K4. Yanlış bir alerjen bilgisi bir sağlık olayıdır ve "ürün
        // sonradan düzenlendi" bir savunma değildir.
        $response->assertOk()->assertJsonPath('data.0.lines.0.allergens', ['celery']);
    }

    public function test_the_kitchen_advances_preparing_and_ready(): void
    {
        $shop = $this->orderingWorkspace('mutfak-ilerlet', role: 'kitchen');
        $orderId = $this->placeOrder($shop, OrderStatus::Confirmed);
        $url = "/api/workspaces/{$shop['workspaceId']}/locations/{$shop['locationId']}/orders/{$orderId}/status";

        $this->actingAs($shop['user'])->putJson($url, ['status' => 'preparing'])->assertOk();
        $this->actingAs($shop['user'])->putJson($url, ['status' => 'ready'])->assertOk();

        $order = DB::table('orders')->where('id', $orderId)->first();

        self::assertSame(OrderStatus::Ready->value, (string) $order->status);
        self::assertNotNull($order->preparing_at);
        self::assertNotNull($order->ready_at);
    }

    public function test_the_kitchen_cannot_hand_the_plate_to_the_table(): void
    {
        $shop = $this->orderingWorkspace('mutfak-teslim', role: 'kitchen');
        $orderId = $this->placeOrder($shop, OrderStatus::Ready);

        /*
            "Teslim edildi" mutfağın değil servisin cümlesidir: tabağı masaya
            götüren kişi bilir. Mutfağa vermek, ocaktan çıkan her tabağı
            masaya gitmiş saymak olurdu.
        */
        $this->actingAs($shop['user'])->putJson(
            "/api/workspaces/{$shop['workspaceId']}/locations/{$shop['locationId']}/orders/{$orderId}/status",
            ['status' => 'delivered'],
        )->assertForbidden();
    }

    public function test_a_step_cannot_be_skipped_even_with_a_hand_written_request(): void
    {
        $shop = $this->orderingWorkspace('mutfak-atlama');
        $orderId = $this->placeOrder($shop, OrderStatus::Confirmed);

        // Şişe su hazırlanmaz diye onaydan doğrudan "hazır"a atlamak cazip
        // görünür; sahibin çizdiği akışta o dal yoktur ve ürün kendi başına
        // yol açmaz.
        $this->actingAs($shop['user'])->putJson(
            "/api/workspaces/{$shop['workspaceId']}/locations/{$shop['locationId']}/orders/{$orderId}/status",
            ['status' => 'ready'],
        )->assertStatus(409);
    }

    public function test_the_panel_cannot_cancel_on_behalf_of_the_guest(): void
    {
        $shop = $this->orderingWorkspace('mutfak-iptal');
        $orderId = $this->placeOrder($shop);

        /*
            İptal misafirin vazgeçmesidir, restoranın kararı değil. Restoran
            reddeder ve SEBEBİNİ yazar. İkisini tek bir "kapatıldı"ya
            indirmek, misafirin ekranında kendi vazgeçtiği siparişle
            reddedilen siparişi aynı cümleye çevirirdi.
        */
        $this->actingAs($shop['user'])->putJson(
            "/api/workspaces/{$shop['workspaceId']}/locations/{$shop['locationId']}/orders/{$orderId}/status",
            ['status' => 'cancelled'],
        )->assertStatus(422)->assertJsonValidationErrors('status');
    }
}
