<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering\Concerns;

use App\Domain\Ordering\OrderStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Sipariş yüzeyinin testleri için ortak kurulum — `docs/115` S4/S5/S6.
 *
 * Kurulum ÜÇ TESTTE DE AYNI olmalı, çünkü üç ekran da aynı satırı okur:
 * garson kuyruğu bekleyeni, mutfak monitörü onaylananı, geçmiş hepsini.
 * Üçüne ayrı fikstür yazsaydık, birinde masa adı, ötekinde alerjen kopyası
 * eksik kalır ve ekranlar aynı siparişi farklı gösterirdi.
 *
 * Fikstür ELLE `DB::table` yazar; sipariş modeli yok ve olmamalı — yazma
 * yolu `EloquentOrderRepository`'ye ait. Test kendi yazma yolunu kurarsa,
 * sınadığı şey ürünün davranışı değil kendi kurgusu olur.
 */
trait BuildsOrderingFixture
{
    /**
     * Bir çalışma alanı, şubesi, menüsü ve masası; istenen rolde bir üye.
     *
     * @return array{user: User, workspaceId: int, locationId: int, menuId: int, tableId: int}
     */
    private function orderingWorkspace(string $seed, string $role = 'owner'): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Restoran '.$seed,
            'slug' => $seed,
            'state' => 'active',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $user->id,
            'role' => $role,
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
            'name' => 'Masa 7',
            'seat_count' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'user' => $user,
            'workspaceId' => $workspaceId,
            'locationId' => $locationId,
            'menuId' => $menuId,
            'tableId' => $tableId,
        ];
    }

    /**
     * Tek satırlı bir sipariş — alerjen KOPYASIYLA birlikte.
     *
     * Alerjen satırın içine yazılır, ürüne bağlanmaz (`docs/115` K4): mutfak
     * monitöründe görünmesi gereken şey siparişin verildiği ANDAKİ gerçektir.
     *
     * @param  array{user: User, workspaceId: int, locationId: int, menuId: int, tableId: int}  $fixture
     * @param  list<string>  $allergens
     */
    private function placeOrder(
        array $fixture,
        OrderStatus $status = OrderStatus::Pending,
        string $productName = 'Beyti',
        array $allergens = ['gluten'],
        int $minutesAgo = 0,
    ): int {
        $at = now()->subMinutes($minutesAgo);

        $orderId = (int) DB::table('orders')->insertGetId([
            'workspace_id' => $fixture['workspaceId'],
            'location_id' => $fixture['locationId'],
            'menu_id' => $fixture['menuId'],
            'dining_table_id' => $fixture['tableId'],
            'status' => $status->value,
            'visitor_key' => str_repeat('a', 64),
            'total_minor_amount' => 4250,
            'currency_code' => 'TRY',
            'placed_at' => $at,
            'confirmed_at' => $status->isVisibleToKitchen() ? $at : null,
            'closed_at' => $status->isFinal() ? $at : null,
            'status_changed_at' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'menu_item_id' => null,
            'product_name' => $productName,
            'unit_price_minor_amount' => 4250,
            'currency_code' => 'TRY',
            'quantity' => 1,
            'line_total_minor_amount' => 4250,
            'allergens' => json_encode($allergens, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        return $orderId;
    }
}
