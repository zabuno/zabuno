<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\Dto\MediaScanResult;
use App\Application\Media\Dto\MediaScanVerdict;
use App\Application\Media\Port\MalwareScannerPort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `docs/49` Faz 4-5 (`docs/98` FF-70): kütüphane listesi zenginleşir,
 * "nerede kullanılıyor?" insan adıyla döner, silme çöpe atar, çöp geri
 * alınır, süresi dolan çöp kalıcı gider, yayında olan hiç gitmez.
 *
 * Kullanıcı yolculuğu: Ayşe "kebap.jpg"i silmek ister → panel "Adana Kebap
 * ürününde kullanılıyor" der → Ayşe yine de siler → görsel çöpe gider,
 * ürün kartı yer tutucuya düşer → ertesi gün "yanlış sildim" der → geri
 * alır, fotoğraf ve ürün bağı aynen döner (dosya hiç silinmemişti).
 */
final class MediaLibraryTrashAndUsagesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private int $workspaceId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->app->instance(MalwareScannerPort::class, new class implements MalwareScannerPort
        {
            public function scan(string $diskPath): MediaScanResult
            {
                return new MediaScanResult(MediaScanVerdict::Clean);
            }
        });

        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->workspaceId = $this->workspace($this->owner);
    }

    private function workspace(User $owner): int
    {
        $id = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'lib-'.Str::lower(Str::random(6)), 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $id, 'user_id' => $owner->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function api(?User $user = null)
    {
        return $this->actingAs($user ?? $this->owner)->withHeaders(['Accept' => 'application/json']);
    }

    private function upload(string $name = 'kebap.jpg', ?int $workspaceId = null): int
    {
        $ws = $workspaceId ?? $this->workspaceId;
        $response = $this->api()->post("/api/workspaces/{$ws}/media", [
            'file' => UploadedFile::fake()->image($name, 400, 400), 'altText' => 'Adana kebap', 'slot' => 'itemImage',
        ]);
        $response->assertStatus(201);

        return (int) $response->json('id');
    }

    /** Gerçek bir ürün + menü satırı: kullanım etiketi "Adana Kebap" olmalı, "#7" değil. */
    private function menuItemNamed(string $name): int
    {
        $seed = Str::lower(Str::random(6));
        // Bir çalışma alanının tek markası vardır; ikinci çağrıda yeniden kullanılır.
        $brandId = (int) (DB::table('brands')->where('workspace_id', $this->workspaceId)->value('id')
            ?? DB::table('brands')->insertGetId([
                'workspace_id' => $this->workspaceId, 'name' => 'Zeytin', 'slug' => 'b-'.$seed, 'locale' => 'tr',
                'timezone' => 'Europe/Istanbul', 'currency' => 'TRY', 'created_at' => now(), 'updated_at' => now(),
            ]));
        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $this->workspaceId, 'brand_id' => $brandId, 'display_name' => 'Şube', 'country_code' => 'TR',
            'city' => 'Adana', 'address_line1' => 'Ziyapaşa Bulvarı 1', 'timezone' => 'Europe/Istanbul',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => 'm-'.$seed, 'workspace_id' => $this->workspaceId, 'location_id' => $locationId, 'name' => 'Ana Menü',
            'state' => 'draft', 'is_indexable' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $categoryId = (int) DB::table('menu_categories')->insertGetId([
            'menu_id' => $menuId, 'name' => 'Kebaplar', 'position' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $productId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $this->workspaceId, 'name' => $name, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) DB::table('menu_items')->insertGetId([
            'category_id' => $categoryId, 'product_id' => $productId, 'price_minor_amount' => 25000, 'currency_code' => 'TRY',
            'is_visible' => 1, 'position' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function usage(int $mediaId, int $menuItemId, ?int $publicationId = null): void
    {
        DB::table('media_usages')->insert([
            'workspace_id' => $this->workspaceId, 'media_asset_id' => $mediaId, 'entity_type' => 'menu_item',
            'entity_id' => $menuItemId, 'slot' => 'itemImage', 'publication_id' => $publicationId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // --- FAZ4-LIBRARY-LIST-ENRICHED-01 ---------------------------------------

    #[Test]
    public function library_list_carries_preview_usage_and_version_counts(): void
    {
        $id = $this->upload();
        $this->usage($id, $this->menuItemNamed('Adana Kebap'));

        $row = collect($this->api()->getJson("/api/workspaces/{$this->workspaceId}/media")->assertOk()->json('data'))
            ->firstWhere('id', $id);

        self::assertSame(1, $row['usageCount']);
        self::assertSame(1, $row['versionCount']);
        self::assertSame('kebap.jpg', $row['originalName']);
        self::assertGreaterThan(0, $row['sizeBytes']);
        self::assertNotEmpty($row['createdAt']);
        self::assertStringStartsWith('/media/r/', (string) $row['previewUrl'], 'Küçük resim değişmez rendition adresi olmalı.');
    }

    // --- FAZ5-USAGES-HUMAN-LABEL-01 ------------------------------------------

    #[Test]
    public function usages_are_listed_with_human_names_and_publication_flag(): void
    {
        $id = $this->upload();
        $this->usage($id, $this->menuItemNamed('Adana Kebap'));
        $this->usage($id, $this->menuItemNamed('Urfa Kebap'), publicationId: 1);

        $usages = $this->api()->getJson("/api/workspaces/{$this->workspaceId}/media/{$id}/usages")->assertOk()->json('usages');

        self::assertSame(['Adana Kebap', 'Urfa Kebap'], array_column($usages, 'label'));
        self::assertSame([false, true], array_column($usages, 'published'));
    }

    #[Test]
    public function another_tenant_cannot_read_usages(): void
    {
        $id = $this->upload();
        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $this->workspace($stranger);

        $this->api($stranger)->getJson("/api/workspaces/{$this->workspaceId}/media/{$id}/usages")->assertStatus(404);
    }

    // --- FAZ5-DETACH-KEEPS-PUBLISHED-01 --------------------------------------

    #[Test]
    public function detach_removes_draft_links_but_never_published_ones(): void
    {
        $id = $this->upload();
        $this->usage($id, $this->menuItemNamed('Adana Kebap'));
        $this->usage($id, $this->menuItemNamed('Urfa Kebap'), publicationId: 1);

        $this->api()->postJson("/api/workspaces/{$this->workspaceId}/media/{$id}/detach")
            ->assertOk()->assertJson(['detached' => 1]);

        self::assertSame(1, DB::table('media_usages')->where('media_asset_id', $id)->count());
        self::assertNotNull(DB::table('media_usages')->where('media_asset_id', $id)->value('publication_id'));
    }

    // --- FAZ5-TRASH-IS-NOT-DELETE-01 ------------------------------------------

    #[Test]
    public function deleting_moves_to_trash_keeps_the_file_and_can_be_restored(): void
    {
        $id = $this->upload();
        $diskPath = (string) DB::table('media_assets')->where('id', $id)->value('disk_path');

        $this->api()->deleteJson("/api/workspaces/{$this->workspaceId}/media/{$id}")->assertStatus(204);

        Storage::disk('local')->assertExists($diskPath);
        self::assertSame('trashed', DB::table('media_assets')->where('id', $id)->value('lifecycle_status'));
        self::assertSame([], $this->api()->getJson("/api/workspaces/{$this->workspaceId}/media")->json('data'));

        $trashed = $this->api()->getJson("/api/workspaces/{$this->workspaceId}/media?trashed=1")->assertOk()->json('data');
        self::assertSame([$id], array_column($trashed, 'id'));
        self::assertSame('trashed', $trashed[0]['lifecycle']);

        $this->api()->postJson("/api/workspaces/{$this->workspaceId}/media/{$id}/restore")->assertOk();

        self::assertNull(DB::table('media_assets')->where('id', $id)->value('deleted_at'));
        self::assertSame('active', DB::table('media_assets')->where('id', $id)->value('lifecycle_status'));
        self::assertSame([$id], array_column($this->api()->getJson("/api/workspaces/{$this->workspaceId}/media")->json('data'), 'id'));
    }

    #[Test]
    public function another_tenant_cannot_restore_from_someone_elses_trash(): void
    {
        $id = $this->upload();
        $this->api()->deleteJson("/api/workspaces/{$this->workspaceId}/media/{$id}")->assertStatus(204);

        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $strangerWs = $this->workspace($stranger);

        $this->api($stranger)->postJson("/api/workspaces/{$strangerWs}/media/{$id}/restore")->assertStatus(404);
        self::assertNotNull(DB::table('media_assets')->where('id', $id)->value('deleted_at'));
    }

    // --- FAZ5-PURGE-AFTER-RETENTION-01 ----------------------------------------

    #[Test]
    public function purge_removes_only_expired_trash_and_never_published_assets(): void
    {
        $fresh = $this->upload('fresh.jpg');
        $old = $this->upload('old.jpg');
        $published = $this->upload('published.jpg');
        $oldPath = (string) DB::table('media_assets')->where('id', $old)->value('disk_path');

        foreach ([$fresh, $old, $published] as $id) {
            $this->api()->deleteJson("/api/workspaces/{$this->workspaceId}/media/{$id}")->assertStatus(204);
        }
        DB::table('media_assets')->whereIn('id', [$old, $published])->update(['deleted_at' => now()->subDays(45)]);
        $this->usage($published, $this->menuItemNamed('Urfa Kebap'), publicationId: 1);

        Artisan::call('media:purge-trash', ['--days' => 30]);

        self::assertNull(DB::table('media_assets')->where('id', $old)->first(), 'Süresi dolan çöp kalıcı silinmeli.');
        Storage::disk('local')->assertMissing($oldPath);
        self::assertNotNull(DB::table('media_assets')->where('id', $fresh)->first(), 'Taze çöp durmalı.');
        self::assertNotNull(DB::table('media_assets')->where('id', $published)->first(), 'Yayında olan asla purge edilmez.');
        self::assertStringContainsString('1', Artisan::output());
    }
}
