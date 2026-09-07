<?php

declare(strict_types=1);

namespace Tests\Feature\Workspace;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * İLK 15 DAKİKA ÖLÇÜLÜYOR — `docs/107` 1.7, `docs/101` §4, `docs/110` §7.
 *
 * Bu uç iki soruya cevap verir ve ikisi de bugüne kadar sunucuda
 * cevapsızdı:
 *
 *   1. Kurulumun BEŞ adımından hangileri bitti? (marka, şube, menüde en az
 *      bir ürün, en az bir yayın, en az bir etkin karekod)
 *   2. Çalışma alanının açılmasından İLK yayına kadar kaç dakika geçti?
 *
 * İkincisi `docs/110` §7'nin "5 dakika mı 15 dakika mı" tartışmasının
 * kapanabilmesi için gereken tek sayıdır ve YENİ TABLO GEREKTİRMEZ: iki
 * zaman damgası zaten kayıtta duruyor (`workspaces.created_at` ve
 * `menu_publications.published_at`). Sayı sunucuda hesaplanır, çünkü iki
 * damganın farkı tarayıcı saatinin doğru olmasını gerektirir ve o saat
 * kullanıcının kendi ayarıdır (`docs/112` §4.1 ile aynı gerekçe).
 *
 * Frozen contract:
 *
 *   GET /api/workspaces/{workspace}/setup-progress
 *     -> 200 {"steps": {"brand": {"done": bool},
 *                       "location": {"done": bool},
 *                       "menu": {"done": bool, "itemCount": int},
 *                       "publication": {"done": bool[, "id": int, "version": int]},
 *                       "qr": {"done": bool, "activeCount": int}},
 *             "doneCount": int, "total": 5
 *             [, "firstPublishedAfterMinutes": int]}   -- yalnız bir yayın VARSA
 *     -> 404 üye olmayan için (403 değil: yerin varlığı da bir bilgidir)
 *     -> 401 oturumsuz için
 *
 * Yayın yoksa süre alanı HİÇ gönderilmez — `null` ya da `0` değil
 * (`docs/112` §3.4 ile aynı ilke: sıfır bir ölçümdür, bilinmeyenin yerine
 * geçemez).
 */
final class SetupProgressTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function workspaceFor(User $owner, string $slug, string $createdAt = '2026-09-06 10:00:00'): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Adana Ocakbaşı',
            'slug' => $slug,
            'state' => 'active',
            'created_by' => $owner->id,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $owner->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $workspaceId;
    }

    /**
     * @return array{brandId:int, locationId:int, menuId:int, categoryId:int}
     */
    private function brandLocationAndEmptyMenu(int $workspaceId, string $slug): array
    {
        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => 'Adana Ocakbaşı',
            'slug' => $slug.'-brand',
            'locale' => 'tr',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId,
            'brand_id' => $brandId,
            'display_name' => 'Seyhan',
            'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul',
            'city' => 'Adana',
            'address_line1' => 'Ziyapaşa Blv. No:1',
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
            'name' => 'Kebaplar',
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['brandId' => $brandId, 'locationId' => $locationId, 'menuId' => $menuId, 'categoryId' => $categoryId];
    }

    private function menuItem(int $workspaceId, int $categoryId, string $name, int $position): void
    {
        $productId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menu_items')->insert([
            'category_id' => $categoryId,
            'product_id' => $productId,
            'price_minor_amount' => 42500,
            'currency_code' => 'TRY',
            'position' => $position,
            'is_visible' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function publication(int $workspaceId, int $menuId, int $locationId, int $userId, int $version, string $publishedAt): int
    {
        return (int) DB::table('menu_publications')->insertGetId([
            'workspace_id' => $workspaceId,
            'menu_id' => $menuId,
            'location_id' => $locationId,
            'version' => $version,
            'state' => $version === 1 ? 'superseded' : 'published',
            'snapshot' => json_encode(['categories' => []], JSON_THROW_ON_ERROR),
            'published_by' => $userId,
            'published_at' => $publishedAt,
            'created_at' => $publishedAt,
            'updated_at' => $publishedAt,
        ]);
    }

    private function qrCode(int $workspaceId, int $locationId, string $state): void
    {
        DB::table('qr_codes')->insert([
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'token' => Str::random(43),
            'state' => $state,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_an_empty_workspace_reports_every_step_undone_and_no_first_publish_time(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceFor($owner, 'setup-empty');

        $response = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/setup-progress");

        $response->assertOk()->assertExactJson([
            'steps' => [
                'brand' => ['done' => false],
                'location' => ['done' => false],
                'menu' => ['done' => false, 'itemCount' => 0],
                'publication' => ['done' => false],
                'qr' => ['done' => false, 'activeCount' => 0],
            ],
            'doneCount' => 0,
            'total' => 5,
        ]);

        // Yayın yoksa süre UYDURULMAZ: alan hiç yoktur.
        self::assertArrayNotHasKey('firstPublishedAfterMinutes', $response->json());
    }

    public function test_a_finished_setup_reports_every_step_done_and_the_minutes_from_workspace_creation_to_the_first_publish(): void
    {
        $owner = $this->verifiedUser();
        // Çalışma alanı 10:00'da açıldı.
        $workspaceId = $this->workspaceFor($owner, 'setup-done', '2026-09-06 10:00:00');
        $ids = $this->brandLocationAndEmptyMenu($workspaceId, 'setup-done');
        $this->menuItem($workspaceId, $ids['categoryId'], 'Adana', 0);
        $this->menuItem($workspaceId, $ids['categoryId'], 'Urfa', 1);

        // İlk yayın 10:27'de, ikincisi ertesi sabah: sayı İLK yayına göredir.
        $this->publication($workspaceId, $ids['menuId'], $ids['locationId'], $owner->id, 1, '2026-09-06 10:27:40');
        $latestId = $this->publication($workspaceId, $ids['menuId'], $ids['locationId'], $owner->id, 2, '2026-09-07 08:00:00');

        // Kapatılmış kod SAYILMAZ: masaya basılabilecek kod yalnız etkin olandır.
        $this->qrCode($workspaceId, $ids['locationId'], 'active');
        $this->qrCode($workspaceId, $ids['locationId'], 'disabled');

        $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/setup-progress")
            ->assertOk()
            ->assertExactJson([
                'steps' => [
                    'brand' => ['done' => true],
                    'location' => ['done' => true],
                    'menu' => ['done' => true, 'itemCount' => 2],
                    'publication' => ['done' => true, 'id' => $latestId, 'version' => 2],
                    'qr' => ['done' => true, 'activeCount' => 1],
                ],
                'doneCount' => 5,
                'total' => 5,
                // 10:00:00 → 10:27:40 = 27 tam dakika; saniye aşağı yuvarlanır.
                'firstPublishedAfterMinutes' => 27,
            ]);
    }

    public function test_a_menu_without_a_single_item_is_not_a_finished_menu_step(): void
    {
        /*
            `docs/70` §2.1: içi boş bir menü yayınlanamaz ve misafire
            gösterecek bir şeyi yoktur. Menünün VARLIĞI adımı bitirmez.
        */
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceFor($owner, 'setup-empty-menu');
        $this->brandLocationAndEmptyMenu($workspaceId, 'setup-empty-menu');

        $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/setup-progress")
            ->assertOk()
            ->assertJsonPath('steps.brand.done', true)
            ->assertJsonPath('steps.location.done', true)
            ->assertJsonPath('steps.menu.done', false)
            ->assertJsonPath('steps.menu.itemCount', 0)
            ->assertJsonPath('doneCount', 2);
    }

    public function test_a_stranger_gets_404_not_403(): void
    {
        /*
            403 "böyle bir çalışma alanı var ama sana kapalı" der ve bu da
            bir bilgidir. Üye olmayan için kurulum kaydı HİÇ YOKTUR.
        */
        $owner = $this->verifiedUser();
        $stranger = $this->verifiedUser();
        $workspaceId = $this->workspaceFor($owner, 'setup-404');

        $this->actingAs($stranger)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/setup-progress")
            ->assertNotFound();
    }

    public function test_an_anonymous_request_is_rejected(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceFor($owner, 'setup-401');

        $this->withHeaders(['Accept' => 'application/json'])
            ->get("/api/workspaces/{$workspaceId}/setup-progress")
            ->assertUnauthorized();
    }
}
