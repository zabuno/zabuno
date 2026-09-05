<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use App\Application\Ordering\Exception\InvalidOrderTransitionException;
use App\Application\Ordering\Exception\OrderNotFoundException;
use App\Application\Ordering\UseCase\ChangeOrderStatus;
use App\Domain\Ordering\OrderActor;
use App\Domain\Ordering\OrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GEÇİŞ TEK YERDE, SINIR SORGUNUN İÇİNDE — `docs/115` S1 (FF-176).
 *
 * Durum makinesinin kuralları saf birim testinde donduruluyor
 * (`Tests\Unit\Domain\OrderStatusMachineTest`). Burada sınanan şey o
 * kuralların VERİTABANINDA nasıl davrandığı: iki garson aynı anda
 * onayladığında ne olur, ve başka bir kiracının/şubenin siparişine
 * dokunulabilir mi.
 *
 * İki soru da ekran kuralı DEĞİLDİR: ekran bir düğmeyi gizleyebilir, istek
 * yine elle gönderilir.
 */
final class OrderTransitionBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_second_confirmation_does_not_silently_pass_and_says_the_state(): void
    {
        [$workspaceId, $locationId, $orderId] = $this->pendingOrder('gecis-ikinci-onay');

        $transitions = app(ChangeOrderStatus::class);

        $transitions->handle($workspaceId, $locationId, $orderId, OrderStatus::Confirmed, OrderActor::Staff);

        try {
            $transitions->handle($workspaceId, $locationId, $orderId, OrderStatus::Confirmed, OrderActor::Staff);
            self::fail('G5: ikinci onay denemesi sessizce geçmemeli.');
        } catch (InvalidOrderTransitionException $e) {
            self::assertSame(OrderStatus::Confirmed, $e->current, 'Ret, siparişin O ANKİ durumunu söylemeli.');
            self::assertSame(OrderStatus::Confirmed, $e->attempted);
        }
    }

    public function test_a_cancel_after_confirmation_is_refused(): void
    {
        [$workspaceId, $locationId, $orderId] = $this->pendingOrder('gecis-gec-iptal');

        $transitions = app(ChangeOrderStatus::class);
        $transitions->handle($workspaceId, $locationId, $orderId, OrderStatus::Confirmed, OrderActor::Staff);

        $this->expectException(InvalidOrderTransitionException::class);
        $transitions->handle($workspaceId, $locationId, $orderId, OrderStatus::Cancelled, OrderActor::Guest);
    }

    public function test_a_rejection_without_a_reason_is_refused(): void
    {
        [$workspaceId, $locationId, $orderId] = $this->pendingOrder('gecis-sebepsiz-ret');

        $this->expectException(InvalidOrderTransitionException::class);

        // G3: sebep misafirin ekranında görünür. Sebepsiz bir ret, misafire
        // "olmadı" demekten ibaret olurdu.
        app(ChangeOrderStatus::class)->handle($workspaceId, $locationId, $orderId, OrderStatus::Rejected, OrderActor::Staff);
    }

    public function test_a_rejection_with_a_reason_is_stored(): void
    {
        [$workspaceId, $locationId, $orderId] = $this->pendingOrder('gecis-sebepli-ret');

        app(ChangeOrderStatus::class)->handle(
            $workspaceId,
            $locationId,
            $orderId,
            OrderStatus::Rejected,
            OrderActor::Staff,
            'Bu masada kimse oturmuyor.',
        );

        $order = DB::table('orders')->where('id', $orderId)->first();

        self::assertSame(OrderStatus::Rejected->value, (string) $order->status);
        self::assertSame('Bu masada kimse oturmuyor.', (string) $order->rejection_reason);
        self::assertNotNull($order->closed_at, 'Kapanan siparişin kapanış anı yazılır.');
    }

    public function test_another_workspace_or_branch_cannot_move_the_order(): void
    {
        [$workspaceId, $locationId, $orderId] = $this->pendingOrder('gecis-sinir');
        [$otherWorkspaceId, $otherLocationId] = $this->pendingOrder('gecis-sinir-komsu');

        $transitions = app(ChangeOrderStatus::class);

        try {
            $transitions->handle($otherWorkspaceId, $locationId, $orderId, OrderStatus::Confirmed, OrderActor::Staff);
            self::fail('Kiracı sınırı sorgunun İÇİNDE olmalı.');
        } catch (OrderNotFoundException) {
            // beklenen
        }

        try {
            $transitions->handle($workspaceId, $otherLocationId, $orderId, OrderStatus::Confirmed, OrderActor::Staff);
            self::fail('Şube sınırı sorgunun İÇİNDE olmalı: komşu şubenin garsonu bu siparişi göremez.');
        } catch (OrderNotFoundException) {
            // beklenen
        }

        self::assertSame(
            OrderStatus::Pending->value,
            (string) DB::table('orders')->where('id', $orderId)->value('status'),
        );
    }

    public function test_each_stage_writes_its_own_moment(): void
    {
        [$workspaceId, $locationId, $orderId] = $this->pendingOrder('gecis-zaman');

        $transitions = app(ChangeOrderStatus::class);
        $transitions->handle($workspaceId, $locationId, $orderId, OrderStatus::Confirmed, OrderActor::Staff);
        $transitions->handle($workspaceId, $locationId, $orderId, OrderStatus::Preparing, OrderActor::Staff);
        $transitions->handle($workspaceId, $locationId, $orderId, OrderStatus::Ready, OrderActor::Staff);
        $transitions->handle($workspaceId, $locationId, $orderId, OrderStatus::Delivered, OrderActor::Staff);

        $order = DB::table('orders')->where('id', $orderId)->first();

        // Her damga bir kez yazılır ve bir daha değişmez: sipariş satırının
        // kendisi, hangi aşamanın ne zaman olduğunun kalıcı kaydıdır
        // (`docs/115` Y2 — geçmiş silinmez).
        foreach (['placed_at', 'confirmed_at', 'preparing_at', 'ready_at', 'closed_at'] as $column) {
            self::assertNotNull($order->{$column}, "{$column} yazılmalı.");
        }
    }

    /** @return array{0:int,1:int,2:int} [workspaceId, locationId, orderId] */
    private function pendingOrder(string $seed): array
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
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => substr(md5($seed), 0, 10),
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'name' => 'Ana Menü',
            'state' => 'draft',
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
            'name' => 'Masa 1',
            'seat_count' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = (int) DB::table('orders')->insertGetId([
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'menu_id' => $menuId,
            'dining_table_id' => $tableId,
            'status' => OrderStatus::Pending->value,
            'visitor_key' => str_repeat('a', 64),
            'total_minor_amount' => 4250,
            'currency_code' => 'TRY',
            'placed_at' => now(),
            'status_changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$workspaceId, $locationId, $orderId];
    }
}
