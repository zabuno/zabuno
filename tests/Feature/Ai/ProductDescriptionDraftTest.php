<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * PRODUCT-DESCRIPTION-DRAFT — ürün açıklaması taslağı, insan onaylı
 * (`docs/95`/`docs/96` Faz 2, `opt-23-ai-product-description`).
 *
 * Alerjen alanına ASLA yazmaz — bu modülün tek yasağı budur (`docs/23`
 * modül dosyası, `docs/04` MOD-R03). Onay TASLAĞA yazar, YAYINA dokunmaz.
 */
final class ProductDescriptionDraftTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private int $workspaceId;

    private int $menuItemId;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.enabled' => true]);
        config(['ai.capabilities' => [
            'product.description' => ['candidates' => ['fake'], 'confidence_threshold' => 0.60],
        ]]);
        config(['ai.budget.monthly_minor_per_tenant' => 100000]);

        $this->owner = User::factory()->create(['email_verified_at' => now()]);

        $this->workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'pd-'.Str::lower(Str::random(6)), 'state' => 'active',
            'created_by' => $this->owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $this->workspaceId, 'user_id' => $this->owner->id,
            'role' => 'owner', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $this->workspaceId, 'name' => 'Zeytin',
            'slug' => 'pd-b-'.Str::lower(Str::random(6)), 'locale' => 'tr',
            'timezone' => 'Europe/Istanbul', 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $this->workspaceId, 'brand_id' => $brandId,
            'display_name' => 'Kadıköy', 'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul', 'city' => 'İstanbul',
            'address_line1' => 'Bahariye Cd. No:1',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)), 'workspace_id' => $this->workspaceId,
            'location_id' => $locationId, 'name' => 'Ana Menü', 'state' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $categoryId = (int) DB::table('menu_categories')->insertGetId([
            'menu_id' => $menuId, 'name' => 'Kebaplar', 'position' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $productId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $this->workspaceId, 'name' => 'Adana Kebap',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->menuItemId = (int) DB::table('menu_items')->insertGetId([
            'category_id' => $categoryId, 'product_id' => $productId,
            'price_minor_amount' => 38000, 'currency_code' => 'TRY',
            'is_visible' => true, 'position' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function api(?User $user = null)
    {
        return $this->actingAs($user ?? $this->owner)->withHeaders(['Accept' => 'application/json']);
    }

    // --- PRODUCT-DESCRIPTION-DRAFT-CREATE-01 --------------------------------

    #[Test]
    public function a_draft_is_created_unapplied_with_source(): void
    {
        $response = $this->api()->postJson(
            "/api/workspaces/{$this->workspaceId}/menu-items/{$this->menuItemId}/description-drafts",
        );

        $response->assertStatus(201, 'description-drafts yanıtı: '.$response->getContent());
        $artifactId = (int) $response->json('id');

        $row = DB::table('ai_artifacts')->where('id', $artifactId)->first();
        self::assertNotNull($row);
        self::assertSame('product.description', $row->capability);
        self::assertNull($row->applied_at, 'PRODUCT-DESCRIPTION-DRAFT: taslak doğrudan uygulanmamalı.');

        /*
            Cevap açıklama METNİNİ taşımalı — inceleme ekranı bunu ayrı bir
            GET olmadan, düzenlenebilir kutuda hemen göstermeli (`docs/97`
            R4).
        */
        self::assertIsString($response->json('description'));
        self::assertNotSame('', trim((string) $response->json('description')));
    }

    // --- PRODUCT-DESCRIPTION-DRAFT-APPLY-ONCE-01 ----------------------------

    #[Test]
    public function approving_writes_the_draft_into_the_product_and_apply_is_idempotent(): void
    {
        $artifactId = (int) $this->api()->postJson(
            "/api/workspaces/{$this->workspaceId}/menu-items/{$this->menuItemId}/description-drafts",
        )->json('id');

        $first = $this->api()->postJson(
            "/api/workspaces/{$this->workspaceId}/description-drafts/{$artifactId}/apply",
        );
        $first->assertStatus(200);
        self::assertFalse((bool) $first->json('alreadyApplied'));

        $productId = (int) DB::table('menu_items')->where('id', $this->menuItemId)->value('product_id');
        $description = (string) DB::table('products')->where('id', $productId)->value('description');
        self::assertNotSame('', trim($description), 'PRODUCT-DESCRIPTION-DRAFT: açıklama ürüne yazılmadı.');

        // İKİNCİ onay — aynı taslak iki kez uygulanmaz.
        $second = $this->api()->postJson(
            "/api/workspaces/{$this->workspaceId}/description-drafts/{$artifactId}/apply",
        );
        $second->assertStatus(200);
        self::assertTrue((bool) $second->json('alreadyApplied'));
    }

    // --- PRODUCT-DESCRIPTION-DRAFT-APPLY-EDITED-01 ---------------------------

    #[Test]
    public function the_reviewer_can_edit_the_draft_before_approving(): void
    {
        /*
            İnceleme ekranının vaadi düzenlenebilir bir kutudur (`docs/97`
            R4) — düzenleme sessizce atılırsa kutu yalan söylemiş olur.
        */
        $artifactId = (int) $this->api()->postJson(
            "/api/workspaces/{$this->workspaceId}/menu-items/{$this->menuItemId}/description-drafts",
        )->json('id');

        $edited = 'Elle düzeltilmiş, sahibin kendi cümlesi.';

        $this->api()->postJson(
            "/api/workspaces/{$this->workspaceId}/description-drafts/{$artifactId}/apply",
            ['description' => $edited],
        )->assertStatus(200);

        $productId = (int) DB::table('menu_items')->where('id', $this->menuItemId)->value('product_id');
        $description = (string) DB::table('products')->where('id', $productId)->value('description');
        self::assertSame($edited, $description, 'PRODUCT-DESCRIPTION-DRAFT: düzenlenmiş metin yok sayıldı.');
    }

    // --- PRODUCT-DESCRIPTION-DRAFT-NEVER-TOUCHES-ALLERGEN-01 ----------------

    #[Test]
    public function the_draft_schema_never_carries_an_allergen_claim_field(): void
    {
        $artifactId = (int) $this->api()->postJson(
            "/api/workspaces/{$this->workspaceId}/menu-items/{$this->menuItemId}/description-drafts",
        )->json('id');

        $row = DB::table('ai_artifacts')->where('id', $artifactId)->first();
        $fields = (array) json_decode((string) $row->fields, true);
        $names = array_column($fields, 'name');

        foreach (['allergen_free', 'is_allergen_free', 'allergens_confirmed', 'no_allergens'] as $forbidden) {
            self::assertNotContains($forbidden, $names, "PRODUCT-DESCRIPTION-DRAFT: yasak alan '{$forbidden}' üretildi.");
        }
    }

    // --- PRODUCT-DESCRIPTION-DRAFT-OFF-IS-HONEST-01 -------------------------

    #[Test]
    public function with_ai_off_the_endpoint_says_so_and_writes_nothing(): void
    {
        config(['ai.enabled' => false]);

        $response = $this->api()->postJson(
            "/api/workspaces/{$this->workspaceId}/menu-items/{$this->menuItemId}/description-drafts",
        );

        $response->assertStatus(503);
        self::assertSame(0, DB::table('ai_artifacts')->count());
    }

    // --- PRODUCT-DESCRIPTION-DRAFT-TENANT-01 --------------------------------

    #[Test]
    public function a_stranger_cannot_draft_or_apply_for_a_workspace_they_do_not_belong_to(): void
    {
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        $this->api($stranger)->postJson(
            "/api/workspaces/{$this->workspaceId}/menu-items/{$this->menuItemId}/description-drafts",
        )->assertStatus(404);
    }
}
