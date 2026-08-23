<?php

declare(strict_types=1);

namespace Tests\Feature\QrDestination;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * S1-WP04b1 RED — public unauthenticated QR resolver contract:
 *
 *   GET /q/{token}    -> 302 Location: /menu/{token}
 *   GET /menu/{token} -> 200 accessible mobile-first HTML rendering only the
 *                        immutable current published snapshot; never draft
 *                        or hidden data.
 *
 * Unknown, malformed, and disabled tokens must all return an
 * indistinguishable 404 on both routes. After a new publication supersedes
 * the current one, the same token/URL renders the new current snapshot.
 * No state change happens on either public GET. Neither route exists yet
 * in routes/web.php (grep confirms no "/q/" or "/menu/" route), so every
 * request below fails RED with a 404 route-not-found response first.
 *
 * Requirement IDs: QR-PUBLIC-RESOLVE-01, QR-PUBLIC-RENDER-01,
 * QR-PUBLIC-404-UNIFORM-01, QR-PUBLIC-NO-DRAFT-LEAK-01,
 * QR-PUBLIC-SUPERSEDE-01, QR-PUBLIC-NO-STATE-CHANGE-01.
 */
final class QrDestinationPublicResolverTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: string} [workspaceId, locationId, menuId, token]
     */
    private function workspaceWithActiveQrCode(User $owner, string $slugSeed): array
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Restoran '.$slugSeed,
            'slug' => $slugSeed,
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
            'name' => 'Marka '.$slugSeed,
            'slug' => $slugSeed.'-brand',
            'locale' => 'tr',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId,
            'brand_id' => $brandId,
            'display_name' => 'Şube '.$slugSeed,
            'country_code' => 'TR',
            'city' => 'İstanbul',
            'address_line1' => 'Adres '.$slugSeed,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $menuId = (int) DB::table('menus')->insertGetId([
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'name' => 'Ana Menü',
            'state' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $categoryId = (int) DB::table('menu_categories')->insertGetId([
            'menu_id' => $menuId,
            'name' => 'Starters',
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $visibleProductId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => 'Kahve',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menu_items')->insert([
            'category_id' => $categoryId,
            'product_id' => $visibleProductId,
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

        DB::table('menu_items')->insert([
            'category_id' => $categoryId,
            'product_id' => $hiddenProductId,
            'price_minor_amount' => 1000,
            'currency_code' => 'TRY',
            'position' => 1,
            'is_visible' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $publicationId = (int) DB::table('menu_publications')->insertGetId([
            'workspace_id' => $workspaceId,
            'menu_id' => $menuId,
            'location_id' => $locationId,
            'version' => 1,
            'state' => 'published',
            'snapshot' => json_encode(['categories' => [['name' => 'Starters', 'menuItems' => [['productName' => 'Kahve', 'priceMinorAmount' => 4250, 'currencyCode' => 'TRY']]]]]),
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

        $created = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-codes",
            ['menuId' => $menuId]
        )->assertStatus(201);
        $token = (string) $created->json('token');

        return [$workspaceId, $locationId, $menuId, $token];
    }

    // --- QR-PUBLIC-RESOLVE-01 -------------------------------------------------

    public function test_q_token_redirects_302_to_menu_token(): void
    {
        $owner = $this->verifiedUser();
        [, , , $token] = $this->workspaceWithActiveQrCode($owner, 'qr-public-1');

        $response = $this->get("/q/{$token}");

        $response->assertStatus(302, 'QR-PUBLIC-RESOLVE-01: /q/{token} 302 Location: /menu/{token} olmalı.');
        $response->assertRedirect("/menu/{$token}");
    }

    // --- QR-PUBLIC-RENDER-01 & QR-PUBLIC-NO-DRAFT-LEAK-01 ----------------------

    public function test_menu_token_renders_200_html_with_the_current_snapshot_and_never_a_hidden_item(): void
    {
        $owner = $this->verifiedUser();
        [, , , $token] = $this->workspaceWithActiveQrCode($owner, 'qr-public-2');

        $response = $this->get("/menu/{$token}");

        $response->assertStatus(200, 'QR-PUBLIC-RENDER-01: /menu/{token} 200 dönmeli.');
        self::assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));

        $html = $response->getContent();
        self::assertIsString($html);
        self::assertStringContainsString('Kahve', $html, 'QR-PUBLIC-RENDER-01: current snapshot\'taki görünür ürün render edilmeli.');
        self::assertStringNotContainsString('Gizli Ürün', $html, 'QR-PUBLIC-NO-DRAFT-LEAK-01: gizli/draft ürün asla sızmamalı.');
    }

    public function test_public_menu_get_requires_no_authentication(): void
    {
        $owner = $this->verifiedUser();
        [, , , $token] = $this->workspaceWithActiveQrCode($owner, 'qr-public-3');

        $response = $this->get("/menu/{$token}");

        self::assertNotSame(401, $response->getStatusCode(), 'QR-PUBLIC-RENDER-01: public GET auth gerektirmemeli.');
        $response->assertStatus(200);
    }

    // --- QR-PUBLIC-404-UNIFORM-01 ----------------------------------------------

    public function test_unknown_malformed_and_disabled_tokens_all_return_an_indistinguishable_404_on_both_routes(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, , , $token] = $this->workspaceWithActiveQrCode($owner, 'qr-public-4');

        $qrCodeId = DB::table('qr_codes')->where('token', $token)->value('id');
        $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(
            "/api/workspaces/{$workspaceId}/qr-codes/{$qrCodeId}/disable"
        )->assertStatus(200);

        $unknownToken = str_repeat('z', 43);
        $malformedToken = 'not-a-valid-token';

        $bodies = [];
        foreach (['unknown' => $unknownToken, 'malformed' => $malformedToken, 'disabled' => $token] as $label => $candidate) {
            $qResponse = $this->get("/q/{$candidate}");
            $qResponse->assertStatus(404, "QR-PUBLIC-404-UNIFORM-01: /q/{$label} 404 olmalı.");

            $menuResponse = $this->get("/menu/{$candidate}");
            $menuResponse->assertStatus(404, "QR-PUBLIC-404-UNIFORM-01: /menu/{$label} 404 olmalı.");

            $bodies[$label] = $menuResponse->getContent();
        }

        self::assertSame($bodies['unknown'], $bodies['malformed'], 'QR-PUBLIC-404-UNIFORM-01: unknown ve malformed 404 gövdeleri ayırt edilemez olmalı.');
        self::assertSame($bodies['unknown'], $bodies['disabled'], 'QR-PUBLIC-404-UNIFORM-01: disabled ve unknown 404 gövdeleri ayırt edilemez olmalı.');
    }

    // --- QR-PUBLIC-SUPERSEDE-01 --------------------------------------------------

    public function test_after_a_new_publication_supersedes_current_the_same_token_and_url_render_the_new_snapshot(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId, $token] = $this->workspaceWithActiveQrCode($owner, 'qr-public-5');

        $first = $this->get("/menu/{$token}");
        $first->assertStatus(200);
        self::assertStringContainsString('Kahve', (string) $first->getContent());

        $newProductId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => 'Yeni Ürün',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $categoryId = (int) DB::table('menu_categories')->where('menu_id', $menuId)->value('id');
        DB::table('menu_items')->insert([
            'category_id' => $categoryId,
            'product_id' => $newProductId,
            'price_minor_amount' => 2000,
            'currency_code' => 'TRY',
            'position' => 2,
            'is_visible' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menu_publications')->where('menu_id', $menuId)->update(['state' => 'superseded']);
        $newPublicationId = (int) DB::table('menu_publications')->insertGetId([
            'workspace_id' => $workspaceId,
            'menu_id' => $menuId,
            'location_id' => $locationId,
            'version' => 2,
            'state' => 'published',
            'snapshot' => json_encode(['categories' => [['name' => 'Starters', 'menuItems' => [
                ['productName' => 'Kahve', 'priceMinorAmount' => 4250, 'currencyCode' => 'TRY'],
                ['productName' => 'Yeni Ürün', 'priceMinorAmount' => 2000, 'currencyCode' => 'TRY'],
            ]]]]),
            'published_by' => $owner->id,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('menu_publication_current_pointers')->where('menu_id', $menuId)->update([
            'current_publication_id' => $newPublicationId,
            'updated_at' => now(),
        ]);

        $second = $this->get("/menu/{$token}");
        $second->assertStatus(200, 'QR-PUBLIC-SUPERSEDE-01: aynı token/URL yeni current snapshot ile 200 render etmeye devam etmeli.');
        self::assertStringContainsString('Yeni Ürün', (string) $second->getContent(), 'QR-PUBLIC-SUPERSEDE-01: yeni current publication içeriği render edilmeli.');
    }

    // --- QR-PUBLIC-NO-STATE-CHANGE-01 --------------------------------------------

    public function test_public_get_requests_perform_no_state_change(): void
    {
        $owner = $this->verifiedUser();
        [, , , $token] = $this->workspaceWithActiveQrCode($owner, 'qr-public-6');

        $beforeCount = DB::table('qr_codes')->count();
        $beforeState = DB::table('qr_codes')->where('token', $token)->value('state');

        $this->get("/q/{$token}");
        $this->get("/menu/{$token}");

        self::assertSame($beforeCount, DB::table('qr_codes')->count(), 'QR-PUBLIC-NO-STATE-CHANGE-01: public GET yeni satır yaratmamalı.');
        self::assertSame($beforeState, DB::table('qr_codes')->where('token', $token)->value('state'), 'QR-PUBLIC-NO-STATE-CHANGE-01: public GET state değiştirmemeli.');
    }
}
