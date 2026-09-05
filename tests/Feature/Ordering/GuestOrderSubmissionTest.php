<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use App\Domain\Analytics\AnalyticsEventType;
use App\Domain\Entitlement\Entitlement;
use App\Domain\Ordering\OrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\GrantsPlanEntitlements;
use Tests\TestCase;

/**
 * MİSAFİR SİPARİŞ GÖNDERME UCU — `docs/115` S2 (FF-176).
 *
 * Bu ucun sınandığı şey "sipariş yazıldı mı" değil; sahibin tarifindeki
 * DÖRT SÖZÜN sunucuda tutulup tutulmadığı:
 *
 * 1. Masa misafirden ALINMAZ, karekoddan okunur (M3).
 * 2. Ad, fiyat ve alerjen siparişe KOPYALANIR; yarınki fiyat dünkü siparişi
 *    değiştirmez (S1).
 * 3. Hak yoksa ya da sipariş alma kapalıysa DÜRÜST RET — boş ekran değil
 *    (Y1, Y3).
 * 4. Basılı karekodun gösterdiği yayın, sahip planını düşürdüğünde
 *    DEĞİŞMEZ (`docs/114` §3 Dalga 6).
 */
final class GuestOrderSubmissionTest extends TestCase
{
    use GrantsPlanEntitlements;
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $scene = [];

    // --- M3: masa karekoddan gelir --------------------------------------------

    public function test_the_guest_never_sends_a_table_and_the_order_still_knows_it(): void
    {
        $this->buildScene('siparis-masa');

        $response = $this->postJson($this->orderPath(), [
            'items' => [['menuItemId' => $this->scene['coffeeItemId'], 'quantity' => 2]],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('status', OrderStatus::Pending->value);

        $order = DB::table('orders')->where('id', $response->json('orderId'))->first();

        self::assertNotNull($order);
        self::assertSame(
            $this->scene['tableId'],
            (int) $order->dining_table_id,
            'M3: masa `qr_codes.dining_table_id` üzerinden gelir; misafire sorulmaz.'
        );
        self::assertSame($this->scene['workspaceId'], (int) $order->workspace_id);
        self::assertSame($this->scene['locationId'], (int) $order->location_id);
    }

    public function test_a_table_id_in_the_payload_is_ignored(): void
    {
        $this->buildScene('siparis-sahte-masa');

        $otherTableId = (int) DB::table('dining_tables')->insertGetId([
            'workspace_id' => $this->scene['workspaceId'],
            'location_id' => $this->scene['locationId'],
            'area_id' => $this->scene['areaId'],
            'name' => 'Masa 99',
            'seat_count' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson($this->orderPath(), [
            'diningTableId' => $otherTableId,
            'items' => [['menuItemId' => $this->scene['coffeeItemId'], 'quantity' => 1]],
        ])->assertStatus(201);

        $order = DB::table('orders')->where('id', $response->json('orderId'))->first();

        self::assertSame(
            $this->scene['tableId'],
            (int) $order->dining_table_id,
            'Masa istemciden alınabilseydi, komşu masaya sipariş açmak bir alan göndermek kadar kolay olurdu.'
        );
    }

    public function test_a_code_that_belongs_to_no_table_refuses_honestly(): void
    {
        $this->buildScene('siparis-giris-kodu');

        DB::table('qr_codes')->where('id', $this->scene['qrCodeId'])->update(['dining_table_id' => null]);

        $this->postJson($this->orderPath(), [
            'items' => [['menuItemId' => $this->scene['coffeeItemId'], 'quantity' => 1]],
        ])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'table_unknown');
    }

    // --- S1: ad, fiyat, alerjen kopyalanır ------------------------------------

    public function test_name_price_and_allergens_are_copied_onto_the_order(): void
    {
        $this->buildScene('siparis-kopya');

        $response = $this->postJson($this->orderPath(), [
            'items' => [['menuItemId' => $this->scene['coffeeItemId'], 'quantity' => 3]],
        ])->assertStatus(201);

        $line = DB::table('order_items')->where('order_id', $response->json('orderId'))->first();

        self::assertNotNull($line);
        self::assertSame('Kahve', (string) $line->product_name);
        self::assertSame(4250, (int) $line->unit_price_minor_amount);
        self::assertSame('TRY', (string) $line->currency_code);
        self::assertSame(3, (int) $line->quantity);
        self::assertSame(12750, (int) $line->line_total_minor_amount);
        self::assertSame(
            ['Süt'],
            json_decode((string) $line->allergens, true),
            'K4: alerjen uyarısı sipariş satırında görünmeli — ürüne bağlı okunsaydı, ürün düzenlendiği gün mutfaktaki kâğıt sessizce değişirdi.'
        );

        self::assertSame(12750, (int) DB::table('orders')->where('id', $response->json('orderId'))->value('total_minor_amount'));
    }

    public function test_tomorrows_price_does_not_change_yesterdays_order(): void
    {
        $this->buildScene('siparis-fiyat-donar');

        $orderId = (int) $this->postJson($this->orderPath(), [
            'items' => [['menuItemId' => $this->scene['coffeeItemId'], 'quantity' => 1]],
        ])->assertStatus(201)->json('orderId');

        DB::table('menu_items')->where('id', $this->scene['coffeeItemId'])->update(['price_minor_amount' => 9900]);
        DB::table('products')->where('id', $this->scene['coffeeProductId'])->update(['name' => 'Filtre Kahve']);

        $line = DB::table('order_items')->where('order_id', $orderId)->first();

        self::assertSame(4250, (int) $line->unit_price_minor_amount, 'Fiyat siparişe KOPYALANIR, bağlanmaz.');
        self::assertSame('Kahve', (string) $line->product_name, 'Ad siparişe KOPYALANIR, bağlanmaz.');
        self::assertSame(4250, (int) DB::table('orders')->where('id', $orderId)->value('total_minor_amount'));
    }

    // --- M7: tükenmiş ürün sipariş yolunda da reddedilir ----------------------

    public function test_an_item_that_ran_out_today_cannot_be_ordered(): void
    {
        $this->buildScene('siparis-bugun-bitti');

        DB::table('menu_items')
            ->where('id', $this->scene['coffeeItemId'])
            ->update(['out_of_stock_since' => now()]);

        $this->postJson($this->orderPath(), [
            'items' => [['menuItemId' => $this->scene['coffeeItemId'], 'quantity' => 1]],
        ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'out_of_stock');

        self::assertSame(0, DB::table('orders')->count(), 'Reddedilen sipariş YAZILMAZ; yarım bir satır bırakmaz.');
    }

    public function test_a_hidden_item_cannot_be_ordered(): void
    {
        $this->buildScene('siparis-gizli');

        $this->postJson($this->orderPath(), [
            'items' => [['menuItemId' => $this->scene['hiddenItemId'], 'quantity' => 1]],
        ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'item_unavailable');
    }

    public function test_an_item_from_another_workspace_menu_cannot_be_ordered(): void
    {
        $this->buildScene('siparis-kiracı-a');
        $foreign = $this->buildScene('siparis-kiracı-b', assign: false);

        $this->postJson($this->orderPath(), [
            'items' => [['menuItemId' => $foreign['coffeeItemId'], 'quantity' => 1]],
        ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'item_unavailable');

        self::assertSame(0, DB::table('orders')->count());
    }

    // --- Y1 / Y3: dürüst ret --------------------------------------------------

    public function test_ordering_switched_off_refuses_with_a_reason(): void
    {
        $this->buildScene('siparis-kapali');

        DB::table('locations')->where('id', $this->scene['locationId'])->update(['accepts_orders' => false]);

        $this->postJson($this->orderPath(), [
            'items' => [['menuItemId' => $this->scene['coffeeItemId'], 'quantity' => 1]],
        ])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'ordering_closed');
    }

    public function test_without_the_ordering_entitlement_the_answer_is_402_and_names_it(): void
    {
        $this->buildScene('siparis-haksiz', entitlements: [Entitlement::QrBulkGeneration]);

        $this->postJson($this->orderPath(), [
            'items' => [['menuItemId' => $this->scene['coffeeItemId'], 'quantity' => 1]],
        ])
            ->assertStatus(402)
            ->assertJsonPath('entitlement', Entitlement::OrderingBasic->value);
    }

    public function test_a_plan_downgrade_does_not_change_what_the_printed_code_already_shows(): void
    {
        // `docs/114` §3 Dalga 6: masadaki kâğıt aynı kâğıttır. Yayın anındaki
        // hak dondurulmuştur; ödeme gecikmesi masadaki misafirin siparişini
        // ANINDA kesmez, plan değişikliği BİR SONRAKİ yayında etkisini
        // gösterir.
        $this->buildScene('siparis-plan-dustu');

        DB::table('subscriptions')->where('workspace_id', $this->scene['workspaceId'])->update(['state' => 'cancelled']);

        $this->postJson($this->orderPath(), [
            'items' => [['menuItemId' => $this->scene['coffeeItemId'], 'quantity' => 1]],
        ])->assertStatus(201);
    }

    // --- Sınırlar -------------------------------------------------------------

    public function test_a_table_cannot_hold_more_open_orders_than_the_cap(): void
    {
        $this->buildScene('siparis-acik-sinir');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson($this->orderPath(), [
                'items' => [['menuItemId' => $this->scene['coffeeItemId'], 'quantity' => 1]],
            ])->assertStatus(201);
        }

        $this->postJson($this->orderPath(), [
            'items' => [['menuItemId' => $this->scene['coffeeItemId'], 'quantity' => 1]],
        ])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'too_many_open_orders');

        // KAPANAN sipariş masayı kilitlemez: akşam boyunca yemek yiyen bir
        // masa ikinci turu veremeseydi ürün masada işe yaramazdı.
        DB::table('orders')
            ->where('dining_table_id', $this->scene['tableId'])
            ->limit(1)
            ->update(['status' => OrderStatus::Delivered->value]);

        $this->postJson($this->orderPath(), [
            'items' => [['menuItemId' => $this->scene['coffeeItemId'], 'quantity' => 1]],
        ])->assertStatus(201);
    }

    public function test_an_unknown_or_disabled_code_gives_the_same_dead_end(): void
    {
        $this->buildScene('siparis-olu-kod');

        $payload = ['items' => [['menuItemId' => $this->scene['coffeeItemId'], 'quantity' => 1]]];

        $unknown = $this->postJson('/q/'.str_repeat('z', 43).'/orders', $payload);
        $unknown->assertStatus(404);

        DB::table('qr_codes')->where('id', $this->scene['qrCodeId'])->update(['state' => 'disabled']);

        $disabled = $this->postJson($this->orderPath(), $payload);
        $disabled->assertStatus(404, 'QR-PUBLIC-404-UNIFORM-01: kapatılmış kod bilinmeyen koddan ayırt edilemez.');
        self::assertSame($unknown->getContent(), $disabled->getContent());
    }

    // --- Ölçüm ----------------------------------------------------------------

    public function test_the_measurement_event_is_written_and_carries_no_product_name_or_price(): void
    {
        $this->buildScene('siparis-olcum');

        $this->postJson($this->orderPath(), [
            'items' => [['menuItemId' => $this->scene['coffeeItemId'], 'quantity' => 2]],
        ])->assertStatus(201);

        $event = DB::table('analytics_events')
            ->where('event_type', AnalyticsEventType::OrderSubmitted->value)
            ->first();

        self::assertNotNull($event, '`docs/112`: sipariş gönderimi taksonomiye EKLENİR — serbest dize yok, enum var.');
        self::assertSame($this->scene['workspaceId'], (int) $event->workspace_id);
        self::assertSame($this->scene['menuId'], (int) $event->menu_id);

        $row = json_encode((array) $event, JSON_UNESCAPED_UNICODE);
        self::assertStringNotContainsString('Kahve', (string) $row, 'Ürün adı ölçüme BASILMAZ.');
        self::assertStringNotContainsString('4250', (string) $row, 'Fiyat ölçüme BASILMAZ.');
    }

    // --- Sahne ----------------------------------------------------------------

    private function orderPath(): string
    {
        return '/q/'.$this->scene['token'].'/orders';
    }

    /**
     * @param  list<Entitlement>|null  $entitlements
     * @return array<string, mixed>
     */
    private function buildScene(string $seed, ?array $entitlements = null, bool $assign = true): array
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

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $owner->id,
            'role' => 'owner',
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
            'accepts_orders' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)),
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'name' => 'Ana Menü',
            'state' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $categoryId = (int) DB::table('menu_categories')->insertGetId([
            'menu_id' => $menuId,
            'name' => 'Sıcak İçecek',
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $coffeeProductId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => 'Kahve',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Alerjen terimi KİRACILAR ARASINDA PAYLAŞILIR (`taxonomy_terms`
        // ad+tür üzerinde tekildir): ikinci sahne aynı terimi yeniden
        // yaratmaya çalışırsa göç değil, testin kurgusu kırılır.
        $milkTermId = (int) (DB::table('taxonomy_terms')
            ->where('name', 'Süt')
            ->where('type', 'allergen')
            ->value('id')
            ?? DB::table('taxonomy_terms')->insertGetId([
                'name' => 'Süt',
                'type' => 'allergen',
                'created_at' => now(),
                'updated_at' => now(),
            ]));

        DB::table('product_allergens')->insert([
            'product_id' => $coffeeProductId,
            'taxonomy_term_id' => $milkTermId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $coffeeItemId = (int) DB::table('menu_items')->insertGetId([
            'category_id' => $categoryId,
            'product_id' => $coffeeProductId,
            'price_minor_amount' => 4250,
            'currency_code' => 'TRY',
            'position' => 0,
            'is_visible' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $hiddenProductId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => 'Gizli Ürün',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $hiddenItemId = (int) DB::table('menu_items')->insertGetId([
            'category_id' => $categoryId,
            'product_id' => $hiddenProductId,
            'price_minor_amount' => 1000,
            'currency_code' => 'TRY',
            'position' => 1,
            'is_visible' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // PLAN ÖNCE VERİLİR, YAYIN SONRA YAPILIR: donan şey yayın anındaki
        // haktır.
        $this->grantEntitlements($workspaceId, $entitlements);

        $publicationId = (int) DB::table('menu_publications')->insertGetId([
            'workspace_id' => $workspaceId,
            'menu_id' => $menuId,
            'location_id' => $locationId,
            'version' => 1,
            'state' => 'published',
            'snapshot' => json_encode([
                'categories' => [[
                    'name' => 'Sıcak İçecek',
                    'menuItems' => [[
                        'menuItemId' => $coffeeItemId,
                        'productName' => 'Kahve',
                        'priceMinorAmount' => 4250,
                        'currencyCode' => 'TRY',
                        'allergens' => ['Süt'],
                    ]],
                ]],
            ]),
            'entitlements' => json_encode(array_map(
                static fn (Entitlement $e): string => $e->value,
                $entitlements ?? Entitlement::cases(),
            )),
            'published_by' => $owner->id,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menu_publication_current_pointers')->insert([
            'menu_id' => $menuId,
            'current_publication_id' => $publicationId,
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
            'name' => 'Masa 12',
            'seat_count' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = Str::random(43);

        $qrCodeId = (int) DB::table('qr_codes')->insertGetId([
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'dining_table_id' => $tableId,
            'token' => $token,
            'state' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $destinationId = (int) DB::table('qr_destinations')->insertGetId([
            'qr_code_id' => $qrCodeId,
            'destination_type' => 'published_menu',
            'menu_id' => $menuId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('qr_code_current_destinations')->insert([
            'qr_code_id' => $qrCodeId,
            'qr_destination_id' => $destinationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $scene = [
            'workspaceId' => $workspaceId,
            'locationId' => $locationId,
            'menuId' => $menuId,
            'coffeeItemId' => $coffeeItemId,
            'coffeeProductId' => $coffeeProductId,
            'hiddenItemId' => $hiddenItemId,
            'areaId' => $areaId,
            'tableId' => $tableId,
            'qrCodeId' => $qrCodeId,
            'token' => $token,
        ];

        if ($assign) {
            $this->scene = $scene;
        }

        return $scene;
    }
}
