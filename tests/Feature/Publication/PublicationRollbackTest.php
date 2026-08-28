<?php

declare(strict_types=1);

namespace Tests\Feature\Publication;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P1-05 RED — yanlış yayından dönmek (`docs/81`).
 *
 * MÜŞTERİ SORUNU. Sahip yanlış fiyat listesini yayınladı; bütün menü %30
 * yanlış ve misafirler şu anda onu okuyor. Bugünkü tek yol taslağı düzeltip
 * YENİDEN yayınlamak — panik anında en yavaş yol, ve düzeltirken ikinci bir
 * hata yapma ihtimali en yüksek olan yol.
 *
 * GEÇMİŞ SİLİNMEZ. Geri alma, eski snapshot'ı YENİ bir yayın olarak yazar.
 * Bir yayını yok saymak, "ne zaman ne yayındaydı" sorusunu cevapsız
 * bırakırdı — oysa yanlış fiyatı gören misafirle tartışan sahip tam olarak
 * bunu sorar.
 *
 * Requirement IDs: PUB-HISTORY-LIST-01, PUB-ROLLBACK-AS-NEW-01,
 * PUB-ROLLBACK-GUEST-IMMEDIATE-01, PUB-ROLLBACK-QR-UNTOUCHED-01,
 * PUB-ROLLBACK-AUTHZ-01, PUB-ROLLBACK-TENANT-01.
 */
final class PublicationRollbackTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:int,2:int,3:int} [owner, workspaceId, menuId, categoryId] */
    private function workspaceWithMenu(string $seed): array
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
            'workspace_id' => $workspaceId, 'name' => 'Zeytin', 'slug' => $seed.'-b',
            'locale' => 'tr', 'timezone' => 'Europe/Istanbul', 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId, 'brand_id' => $brandId,
            'display_name' => 'Kadıköy', 'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul', 'city' => 'İstanbul',
            'address_line1' => 'Bahariye Cd. No:1',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)), 'workspace_id' => $workspaceId,
            'location_id' => $locationId, 'name' => 'Ana Menü', 'state' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $categoryId = (int) DB::table('menu_categories')->insertGetId([
            'menu_id' => $menuId, 'name' => 'Balıklar', 'position' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $productId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Levrek',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('menu_items')->insert([
            'category_id' => $categoryId, 'product_id' => $productId,
            'price_minor_amount' => 42000, 'currency_code' => 'TRY',
            'position' => 0, 'is_visible' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$owner, $workspaceId, $menuId, $categoryId];
    }

    private function api(User $user)
    {
        return $this->actingAs($user)->withHeaders(['Accept' => 'application/json']);
    }

    private function publish(User $owner, int $workspaceId, int $menuId): array
    {
        return $this->api($owner)->postJson("/api/workspaces/{$workspaceId}/menu/{$menuId}/publications")->json();
    }

    private function setPriceTo(int $menuId, int $minorAmount): void
    {
        DB::table('menu_items')
            ->whereIn('category_id', DB::table('menu_categories')->where('menu_id', $menuId)->pluck('id'))
            ->update(['price_minor_amount' => $minorAmount]);
    }

    // --- PUB-HISTORY-LIST-01 ----------------------------------------------

    public function test_the_owner_can_see_which_version_is_live(): void
    {
        [$owner, $workspaceId, $menuId] = $this->workspaceWithMenu('rollback-list');

        $this->publish($owner, $workspaceId, $menuId);
        $this->setPriceTo($menuId, 54600);
        $second = $this->publish($owner, $workspaceId, $menuId);

        $response = $this->api($owner)->getJson("/api/workspaces/{$workspaceId}/menu/{$menuId}/publications");

        $response->assertOk();

        $rows = $response->json('data');
        self::assertCount(2, $rows);

        // EN YENİ ÖNCE: paniğe kapılan sahip listenin dibine inmez.
        self::assertSame(2, $rows[0]['version']);
        self::assertSame(1, $rows[1]['version']);

        self::assertTrue($rows[0]['isLive'], 'PUB-HISTORY-LIST-01: canlı sürüm işaretli olmalı.');
        self::assertFalse($rows[1]['isLive']);
        self::assertNotEmpty($rows[0]['publishedAt']);
        self::assertSame($second['id'], $rows[0]['id']);
    }

    // --- PUB-ROLLBACK-AS-NEW-01 / GUEST-IMMEDIATE-01 ----------------------

    public function test_going_back_writes_a_new_publication_and_the_guest_sees_it_at_once(): void
    {
        [$owner, $workspaceId, $menuId] = $this->workspaceWithMenu('rollback-back');

        $good = $this->publish($owner, $workspaceId, $menuId);

        // Yanlış fiyat listesi yayına girdi.
        $this->setPriceTo($menuId, 54600);
        $this->publish($owner, $workspaceId, $menuId);

        $publicKey = (string) DB::table('menus')->where('id', $menuId)->value('public_key');
        self::assertStringContainsString(
            '546.00',
            $this->followingRedirects()->get("/menu/{$publicKey}")->getContent(),
            'Testin öncülü: yanlış fiyat gerçekten yayında olmalı.'
        );

        $response = $this->api($owner)->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/{$good['id']}/restore"
        );

        $response->assertStatus(201);

        // GEÇMİŞ SİLİNMEZ: geri alma ÜÇÜNCÜ bir yayındır.
        self::assertSame(3, $response->json('version'));
        self::assertSame(3, DB::table('menu_publications')->where('menu_id', $menuId)->count());
        self::assertSame(
            $good['snapshot'],
            $response->json('snapshot'),
            'PUB-ROLLBACK-AS-NEW-01: geri alınan yayın, seçilen sürümün snapshot\'ını taşımalı.'
        );

        // Misafir ANINDA doğru fiyatı görür.
        $html = $this->followingRedirects()->get("/menu/{$publicKey}")->getContent();
        self::assertStringContainsString('420.00', $html);
        self::assertStringNotContainsString('546.00', $html);
    }

    // --- PUB-ROLLBACK-QR-UNTOUCHED-01 -------------------------------------

    public function test_going_back_does_not_touch_the_printed_code(): void
    {
        [$owner, $workspaceId, $menuId] = $this->workspaceWithMenu('rollback-qr');

        $good = $this->publish($owner, $workspaceId, $menuId);
        $publicKeyBefore = (string) DB::table('menus')->where('id', $menuId)->value('public_key');

        $this->setPriceTo($menuId, 54600);
        $this->publish($owner, $workspaceId, $menuId);

        $this->api($owner)->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/{$good['id']}/restore"
        )->assertStatus(201);

        self::assertSame(
            $publicKeyBefore,
            (string) DB::table('menus')->where('id', $menuId)->value('public_key'),
            'PUB-ROLLBACK-QR-UNTOUCHED-01: masadaki basılı kodun adresi geri almadan etkilenmez.'
        );
    }

    // --- PUB-ROLLBACK-AUTHZ-01 --------------------------------------------

    public function test_a_read_only_member_can_read_history_but_cannot_roll_back(): void
    {
        [$owner, $workspaceId, $menuId] = $this->workspaceWithMenu('rollback-authz');
        $good = $this->publish($owner, $workspaceId, $menuId);

        $member = User::factory()->create(['email_verified_at' => now()]);
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId, 'user_id' => $member->id, 'role' => 'member',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->api($member)->getJson("/api/workspaces/{$workspaceId}/menu/{$menuId}/publications")->assertOk();

        // Geri alma YAYINLAMAKTIR: aynı izni ister.
        $this->api($member)->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/{$good['id']}/restore"
        )->assertStatus(403);
    }

    // --- PUB-ROLLBACK-TENANT-01 -------------------------------------------

    public function test_a_publication_from_another_restaurant_cannot_be_restored_here(): void
    {
        [$owner, $workspaceId, $menuId] = $this->workspaceWithMenu('rollback-mine');
        $this->publish($owner, $workspaceId, $menuId);

        [$otherOwner, $otherWorkspaceId, $otherMenuId] = $this->workspaceWithMenu('rollback-theirs');
        $theirs = $this->publish($otherOwner, $otherWorkspaceId, $otherMenuId);

        $this->api($owner)->postJson(
            "/api/workspaces/{$workspaceId}/menu/{$menuId}/publications/{$theirs['id']}/restore"
        )->assertNotFound();

        self::assertSame(1, DB::table('menu_publications')->where('menu_id', $menuId)->count());
    }
}
