<?php

declare(strict_types=1);

namespace Tests\Feature\MenuCatalog;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\GrantsPlanEntitlements;
use Tests\TestCase;

/**
 * MENÜYÜ İŞLETMEK — `docs/73` (P0-01).
 *
 * Ürün bugün bir menüyü YAYIMLAYABİLİYOR ama İŞLETEMİYOR: silme, ad düzeltme
 * ve sıralama uç noktaları yok. "Mercimek Çorbsı" yazan bir sahibin
 * düzeltmek için tek yolu ürünü gizleyip doğrusunu yeniden eklemek — ve
 * yanlış olan veritabanında sonsuza kadar kalıyor.
 *
 * `position` sütunları göçte VAR ve `unique(menu_id, position)` /
 * `unique(category_id, position)` ile korunuyor: sıralama veri modelinde
 * tasarlanmış, yüzeyi yazılmamış.
 */
final class MenuOperationsTest extends TestCase
{
    use GrantsPlanEntitlements;
    use RefreshDatabase;

    /** @return array{owner: User, workspace: int, location: int, menu: int} */
    private function restaurant(string $seed): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => $seed, 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId, 'user_id' => $owner->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Zeytin', 'slug' => $seed.'-brand',
            'locale' => 'tr', 'timezone' => 'Europe/Istanbul', 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId, 'brand_id' => $brandId,
            'display_name' => 'Kadıköy', 'country_code' => 'TR', 'timezone' => 'Europe/Istanbul',
            'city' => 'İstanbul', 'address_line1' => 'Bahariye Cd. 1',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->grantEntitlements($workspaceId);

        $menuId = (int) $this->actingAs($owner)->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/menu",
            ['name' => 'Ana Menü'],
        )->assertStatus(201)->json('id');

        return ['owner' => $owner, 'workspace' => $workspaceId, 'location' => $locationId, 'menu' => $menuId];
    }

    private function addCategory(array $r, string $name): int
    {
        return (int) $this->actingAs($r['owner'])->postJson(
            "/api/workspaces/{$r['workspace']}/menu/{$r['menu']}/categories",
            ['name' => $name],
        )->assertStatus(201)->json('id');
    }

    private function addItem(array $r, int $categoryId, string $name, int $price = 4500): int
    {
        return (int) $this->actingAs($r['owner'])->postJson(
            "/api/workspaces/{$r['workspace']}/menu-categories/{$categoryId}/menu-entries",
            ['productName' => $name, 'price' => number_format($price / 100, 2, '.', ''), 'currency' => 'TRY'],
        )->assertStatus(201)->json('id');
    }

    private function tree(array $r): array
    {
        return (array) $this->actingAs($r['owner'])->getJson(
            "/api/workspaces/{$r['workspace']}/brand/locations/{$r['location']}/menu",
        )->assertOk()->json();
    }

    // --- MENU-OPS-DELETE-01 -------------------------------------------------

    /**
     * Yanlış yazılan bir ürün SİLİNEBİLMELİ. Bugün tek yol gizlemek ve
     * doğrusunu yeniden eklemek; menü her hatada biraz daha çöple doluyor.
     */
    public function test_an_owner_can_delete_a_menu_item(): void
    {
        $r = $this->restaurant('ops-delete-item');
        $categoryId = $this->addCategory($r, 'Çorbalar');
        $wrong = $this->addItem($r, $categoryId, 'Mercimek Çorbsı');
        $right = $this->addItem($r, $categoryId, 'Mercimek Çorbası');

        $this->actingAs($r['owner'])
            ->deleteJson("/api/workspaces/{$r['workspace']}/menu-items/{$wrong}")
            ->assertOk();

        $items = $this->tree($r)['categories'][0]['menuItems'];
        $names = array_column($items, 'productName');

        self::assertNotContains('Mercimek Çorbsı', $names);
        self::assertContains('Mercimek Çorbası', $names);
        self::assertCount(1, $items);
        self::assertSame($right, $items[0]['id']);
    }

    public function test_an_owner_can_delete_a_category_with_its_items(): void
    {
        $r = $this->restaurant('ops-delete-category');
        $summer = $this->addCategory($r, 'Yaz Menüsü');
        $this->addItem($r, $summer, 'Limonata');
        $keep = $this->addCategory($r, 'Çorbalar');

        $this->actingAs($r['owner'])
            ->deleteJson("/api/workspaces/{$r['workspace']}/menu-categories/{$summer}")
            ->assertOk();

        $categories = $this->tree($r)['categories'];

        self::assertCount(1, $categories);
        self::assertSame($keep, $categories[0]['id']);
    }

    /**
     * SİLME GEÇMİŞİ BOZMAZ.
     *
     * Yayınlanmış sürüm bir anlık görüntüdür. Bir ürünü bugün silmek, dün
     * yayınlanmış menüyü değiştirirse, basılı QR'ı tarayan misafir bir
     * gerçeği değil bugünün taslağını görür.
     */
    public function test_deleting_an_item_leaves_an_existing_publication_untouched(): void
    {
        $r = $this->restaurant('ops-delete-snapshot');
        $categoryId = $this->addCategory($r, 'Çorbalar');
        $itemId = $this->addItem($r, $categoryId, 'Mercimek Çorbası');

        $this->actingAs($r['owner'])->putJson(
            "/api/workspaces/{$r['workspace']}/menu-items/{$itemId}/visibility",
            ['isVisible' => true],
        )->assertOk();

        $this->actingAs($r['owner'])->postJson(
            "/api/workspaces/{$r['workspace']}/menu/{$r['menu']}/publications",
        )->assertStatus(201);

        $before = (string) DB::table('menu_publications')->where('menu_id', $r['menu'])->value('snapshot');

        $this->actingAs($r['owner'])
            ->deleteJson("/api/workspaces/{$r['workspace']}/menu-items/{$itemId}")
            ->assertOk();

        $after = (string) DB::table('menu_publications')->where('menu_id', $r['menu'])->value('snapshot');

        self::assertSame($before, $after, 'MENU-OPS-DELETE-SNAPSHOT-01: yayın bayt bayt aynı kalmalı.');

        /*
            Snapshot JSON'unda Türkçe harfler `\uXXXX` olarak kaçırılır;
            ham metinde aramak, saklanan biçimi değil beklediğimiz biçimi
            ölçmek olurdu. Çözülüp içerik üzerinden bakılır.
        */
        $decoded = json_decode($after, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(
            'Mercimek Çorbası',
            $decoded['categories'][0]['menuItems'][0]['productName'],
            'MENU-OPS-DELETE-SNAPSHOT-01: silinen ürün eski yayında DURMALI.',
        );
    }

    // --- MENU-OPS-RENAME-01 -------------------------------------------------

    public function test_an_owner_can_correct_a_category_name(): void
    {
        $r = $this->restaurant('ops-rename-category');
        $categoryId = $this->addCategory($r, 'Çorbalr');

        $this->actingAs($r['owner'])->putJson(
            "/api/workspaces/{$r['workspace']}/menu-categories/{$categoryId}",
            ['name' => 'Çorbalar'],
        )->assertOk();

        self::assertSame('Çorbalar', $this->tree($r)['categories'][0]['name']);
    }

    public function test_an_owner_can_correct_an_item_name(): void
    {
        $r = $this->restaurant('ops-rename-item');
        $categoryId = $this->addCategory($r, 'Çorbalar');
        $itemId = $this->addItem($r, $categoryId, 'Mercimek Çorbsı');

        $this->actingAs($r['owner'])->putJson(
            "/api/workspaces/{$r['workspace']}/menu-items/{$itemId}",
            ['productName' => 'Mercimek Çorbası'],
        )->assertOk();

        self::assertSame(
            'Mercimek Çorbası',
            $this->tree($r)['categories'][0]['menuItems'][0]['productName'],
        );
    }

    /** Boş ad bir düzeltme değil, bir kayıptır. */
    public function test_a_blank_name_is_refused(): void
    {
        $r = $this->restaurant('ops-rename-blank');
        $categoryId = $this->addCategory($r, 'Çorbalar');

        $this->actingAs($r['owner'])->putJson(
            "/api/workspaces/{$r['workspace']}/menu-categories/{$categoryId}",
            ['name' => '   '],
        )->assertStatus(422);
    }

    // --- MENU-OPS-REORDER-01 ------------------------------------------------

    /**
     * Sıralama TEK İSTEKTE ve kısıt ihlali olmadan.
     *
     * `unique(category_id, position)` yüzünden satırları tek tek güncellemek
     * yolun ortasında çakışır: ikinci ürünü birinci sıraya taşımak, birinci
     * ürün hâlâ oradayken imkânsızdır. Uç nokta bunu iki aşamada çözmeli.
     */
    public function test_items_can_be_fully_reversed_in_one_request(): void
    {
        $r = $this->restaurant('ops-reorder-items');
        $categoryId = $this->addCategory($r, 'Çorbalar');

        $ids = [];
        for ($i = 1; $i <= 12; $i++) {
            $ids[] = $this->addItem($r, $categoryId, 'Ürün '.$i);
        }

        $reversed = array_reverse($ids);

        $this->actingAs($r['owner'])->putJson(
            "/api/workspaces/{$r['workspace']}/menu-categories/{$categoryId}/item-order",
            ['menuItemIds' => $reversed],
        )->assertOk();

        $items = $this->tree($r)['categories'][0]['menuItems'];

        self::assertSame($reversed, array_column($items, 'id'));
    }

    public function test_categories_can_be_reordered(): void
    {
        $r = $this->restaurant('ops-reorder-categories');
        $drinks = $this->addCategory($r, 'İçecekler');
        $soups = $this->addCategory($r, 'Çorbalar');

        $this->actingAs($r['owner'])->putJson(
            "/api/workspaces/{$r['workspace']}/menu/{$r['menu']}/category-order",
            ['categoryIds' => [$soups, $drinks]],
        )->assertOk();

        self::assertSame(
            [$soups, $drinks],
            array_column($this->tree($r)['categories'], 'id'),
        );
    }

    /**
     * Eksik ya da yabancı bir kimlik listesi REDDEDİLİR: kısmî bir sıralama,
     * listelenmeyen satırları öngörülemez bir yere bırakır.
     */
    public function test_a_partial_order_list_is_refused(): void
    {
        $r = $this->restaurant('ops-reorder-partial');
        $categoryId = $this->addCategory($r, 'Çorbalar');
        $first = $this->addItem($r, $categoryId, 'Bir');
        $this->addItem($r, $categoryId, 'İki');

        $this->actingAs($r['owner'])->putJson(
            "/api/workspaces/{$r['workspace']}/menu-categories/{$categoryId}/item-order",
            ['menuItemIds' => [$first]],
        )->assertStatus(422);
    }

    // --- MENU-OPS-TENANT-01 -------------------------------------------------

    /** Başka kiracının ürünü SİLİNEMEZ ve varlığı sızmaz. */
    public function test_deleting_another_tenants_item_is_enumeration_safe(): void
    {
        $mine = $this->restaurant('ops-tenant-a-'.Str::lower(Str::random(4)));
        $theirs = $this->restaurant('ops-tenant-b-'.Str::lower(Str::random(4)));

        $categoryId = $this->addCategory($theirs, 'Çorbalar');
        $itemId = $this->addItem($theirs, $categoryId, 'Mercimek');

        $this->actingAs($mine['owner'])
            ->deleteJson("/api/workspaces/{$mine['workspace']}/menu-items/{$itemId}")
            ->assertStatus(404);

        self::assertDatabaseHas('menu_items', ['id' => $itemId]);
    }

    /**
     * Silme, DÜZENLEME iznine bağlıdır — ayrı bir "silme" izni uydurulmadı.
     *
     * Gerekçesi ölçülebilir: silme yalnız taslağı etkiler ve yayınlanmış
     * sürüm bayt bayt aynı kalır (yukarıdaki test). Yani silme, yayınlamak
     * gibi misafirin gördüğünü değiştiren bir iş değildir.
     *
     * Salt okunur bir üye ise silemez.
     */
    public function test_a_read_only_member_cannot_delete(): void
    {
        $r = $this->restaurant('ops-member-delete');
        $categoryId = $this->addCategory($r, 'Çorbalar');
        $itemId = $this->addItem($r, $categoryId, 'Mercimek');

        $member = User::factory()->create(['email_verified_at' => now()]);
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $r['workspace'], 'user_id' => $member->id, 'role' => 'member',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($member)
            ->deleteJson("/api/workspaces/{$r['workspace']}/menu-items/{$itemId}")
            ->assertStatus(403);

        self::assertDatabaseHas('menu_items', ['id' => $itemId]);
    }
}
