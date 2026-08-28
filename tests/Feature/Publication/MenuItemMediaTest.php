<?php

declare(strict_types=1);

namespace Tests\Feature\Publication;

use App\Application\Media\Dto\MediaScanResult;
use App\Application\Media\Dto\MediaScanVerdict;
use App\Application\Media\Port\MalwareScannerPort;
use App\Application\Media\Port\MediaAssetProcessorPort;
use App\Application\Media\Port\MediaRepositoryPort;
use App\Application\Media\UseCase\ProcessAcceptedMediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P0-04 RED — ürün açıklaması ve görseli yayın snapshot'ına bağlanır
 * (`docs/77`).
 *
 * MÜŞTERİ SORUNU. Sahip menüsünü dijitale taşımasının en somut sebebini
 * kullanamıyor: fotoğraf. "Adana Kebap · 380,00 TL" bir satırdır;
 * fotoğraflı ve açıklamalı bir kart satış aracıdır. Bugün ürün için ne
 * açıklama alanı, ne görsel bağı var.
 *
 * GÖRSEL SÜRÜME BAĞLANIR, varlığa değil. Yayınlanmış bir menü, sahibin
 * sonradan düzenlediği bir fotoğrafı habersiz göstermemeli — yayın, "bunu
 * onayladım" denen hâldir.
 *
 * Requirement IDs: MENU-ITEM-DESCRIPTION-01, MENU-ITEM-IMAGE-BIND-01,
 * MENU-ITEM-IMAGE-FROZEN-01, MENU-ITEM-IMAGE-SRCSET-01,
 * BRAND-LOGO-ON-MENU-01, MEDIA-USAGE-ON-PUBLISH-01.
 */
final class MenuItemMediaTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private int $workspaceId;

    private int $brandId;

    private int $menuId;

    private int $menuItemId;

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

        $this->workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'zeytin-media-'.Str::lower(Str::random(6)),
            'state' => 'active', 'created_by' => $this->owner->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $this->workspaceId, 'user_id' => $this->owner->id,
            'role' => 'owner', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $this->workspaceId, 'name' => 'Zeytin Restoranları',
            'slug' => 'zeytin-'.Str::lower(Str::random(6)), 'locale' => 'tr',
            'timezone' => 'Europe/Istanbul', 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $this->workspaceId, 'brand_id' => $this->brandId,
            'display_name' => 'Kadıköy', 'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul', 'city' => 'İstanbul',
            'address_line1' => 'Bahariye Cd. No:1',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)), 'workspace_id' => $this->workspaceId,
            'location_id' => $locationId, 'name' => 'Ana Menü', 'state' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $categoryId = (int) DB::table('menu_categories')->insertGetId([
            'menu_id' => $this->menuId, 'name' => 'Kebaplar', 'position' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $productId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $this->workspaceId, 'name' => 'Adana Kebap',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->menuItemId = (int) DB::table('menu_items')->insertGetId([
            'category_id' => $categoryId, 'product_id' => $productId,
            'price_minor_amount' => 38000, 'currency_code' => 'TRY',
            'position' => 0, 'is_visible' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function api(): \Illuminate\Testing\TestCase|TestCase
    {
        return $this->actingAs($this->owner)->withHeaders(['Accept' => 'application/json']);
    }

    private function uploadImage(string $slot, int $size = 1200): int
    {
        return (int) $this->api()->post(
            "/api/workspaces/{$this->workspaceId}/media",
            [
                'file' => UploadedFile::fake()->image('kebap.jpg', $size, $size),
                'altText' => 'Kömür ateşinde Adana kebap',
                'slot' => $slot,
            ]
        )->json('id');
    }

    private function publish(): array
    {
        return $this->api()->postJson(
            "/api/workspaces/{$this->workspaceId}/menu/{$this->menuId}/publications"
        )->json();
    }

    // --- MENU-ITEM-DESCRIPTION-01 -----------------------------------------

    public function test_a_product_can_carry_a_description_that_reaches_the_guest(): void
    {
        $this->api()->putJson("/api/workspaces/{$this->workspaceId}/menu-items/{$this->menuItemId}", [
            'productName' => 'Adana Kebap',
            'description' => 'Kömür ateşinde, acılı, yanında bulgur pilavı ve közlenmiş biber.',
        ])->assertOk();

        $item = $this->publish()['snapshot']['categories'][0]['menuItems'][0];

        self::assertSame(
            'Kömür ateşinde, acılı, yanında bulgur pilavı ve közlenmiş biber.',
            $item['description'] ?? null,
            'MENU-ITEM-DESCRIPTION-01: açıklama yayın snapshot\'ına girmeli.'
        );

        $html = view('public-menu', ['snapshot' => $this->publish()['snapshot']])->render();
        self::assertStringContainsString('Kömür ateşinde, acılı', $html);
    }

    public function test_an_item_without_a_description_does_not_break_the_row(): void
    {
        $item = $this->publish()['snapshot']['categories'][0]['menuItems'][0];

        self::assertNull($item['description'] ?? null);

        $html = view('public-menu', ['snapshot' => $this->publish()['snapshot']])->render();
        self::assertStringContainsString('Adana Kebap', $html);
        self::assertDoesNotMatchRegularExpression('#<span class="qr-menu-item-description"#', $html);
    }

    // --- MENU-ITEM-IMAGE-BIND-01 ------------------------------------------

    public function test_an_image_is_bound_to_a_menu_item_as_a_usage_row(): void
    {
        $mediaId = $this->uploadImage('itemImage');

        $this->api()->putJson(
            "/api/workspaces/{$this->workspaceId}/menu-items/{$this->menuItemId}/image",
            ['mediaAssetId' => $mediaId]
        )->assertOk();

        $usage = DB::table('media_usages')
            ->where('media_asset_id', $mediaId)
            ->whereNull('publication_id')
            ->first();

        self::assertNotNull($usage, 'MENU-ITEM-IMAGE-BIND-01: bağ bir kullanım satırı yaratmalı.');
        self::assertSame('menu_item', (string) $usage->entity_type);
        self::assertSame($this->menuItemId, (int) $usage->entity_id);
        self::assertSame('itemImage', (string) $usage->slot);
        self::assertNotNull(
            $usage->media_version_id,
            'MENU-ITEM-IMAGE-BIND-01: bağ SÜRÜME yapılır, varlığa değil.'
        );
    }

    public function test_detaching_an_image_removes_the_binding(): void
    {
        $mediaId = $this->uploadImage('itemImage');

        $this->api()->putJson("/api/workspaces/{$this->workspaceId}/menu-items/{$this->menuItemId}/image",
            ['mediaAssetId' => $mediaId])->assertOk();

        $this->api()->putJson("/api/workspaces/{$this->workspaceId}/menu-items/{$this->menuItemId}/image",
            ['mediaAssetId' => null])->assertOk();

        self::assertSame(0, DB::table('media_usages')
            ->where('entity_type', 'menu_item')->where('entity_id', $this->menuItemId)
            ->whereNull('publication_id')->count());
    }

    // --- MENU-ITEM-IMAGE-SRCSET-01 ----------------------------------------

    public function test_the_guest_page_downloads_the_size_it_needs(): void
    {
        $mediaId = $this->uploadImage('itemImage');
        $this->api()->putJson("/api/workspaces/{$this->workspaceId}/menu-items/{$this->menuItemId}/image",
            ['mediaAssetId' => $mediaId])->assertOk();

        $snapshot = $this->publish()['snapshot'];
        $image = $snapshot['categories'][0]['menuItems'][0]['image'] ?? null;

        self::assertIsArray($image, 'MENU-ITEM-IMAGE-SRCSET-01: snapshot görsel taşımalı.');
        self::assertSame(
            [320, 480, 640, 960],
            array_map(static fn (array $s): int => $s['width'], $image['sources']),
            'MENU-ITEM-IMAGE-SRCSET-01: slotun tüm boyutları sunulmalı.'
        );
        self::assertNotEmpty($image['altText']);

        $html = view('public-menu', ['snapshot' => $snapshot])->render();

        self::assertMatchesRegularExpression('#<img[^>]+srcset="[^"]*320w[^"]*960w[^"]*"#', $html);
        self::assertMatchesRegularExpression('#<img[^>]+loading="lazy"#', $html);
        // Genişlik ve yükseklik OLMADAN görsel yüklenirken sayfa zıplar;
        // misafir okuduğu satırı kaybeder.
        self::assertMatchesRegularExpression('#<img[^>]+width="\d+"[^>]+height="\d+"#', $html);
        self::assertStringContainsString('Kömür ateşinde Adana kebap', $html);
    }

    // --- MENU-ITEM-IMAGE-FROZEN-01 ----------------------------------------

    public function test_a_published_menu_keeps_showing_the_version_it_was_published_with(): void
    {
        $mediaId = $this->uploadImage('itemImage');
        $this->api()->putJson("/api/workspaces/{$this->workspaceId}/menu-items/{$this->menuItemId}/image",
            ['mediaAssetId' => $mediaId])->assertOk();

        $first = $this->publish();
        $firstVersionId = $first['snapshot']['categories'][0]['menuItems'][0]['image']['versionId'];

        // Sahip aynı varlığın YENİ bir sürümünü üretir (yeniden işleme).
        DB::table('media_assets')->where('id', $mediaId)->update(['status' => 'accepted']);
        (new ProcessAcceptedMediaAsset(
            $this->app->make(MediaAssetProcessorPort::class),
            $this->app->make(MediaRepositoryPort::class),
        ))($this->workspaceId, $mediaId);

        $versions = DB::table('media_versions')->where('media_asset_id', $mediaId)->count();
        self::assertSame(2, $versions, 'Testin öncülü: gerçekten ikinci bir sürüm üretilmiş olmalı.');

        $stored = $this->api()->getJson(
            "/api/workspaces/{$this->workspaceId}/menu/{$this->menuId}/publications/current"
        )->json();

        self::assertSame(
            $firstVersionId,
            $stored['snapshot']['categories'][0]['menuItems'][0]['image']['versionId'],
            'MENU-ITEM-IMAGE-FROZEN-01: yayınlanmış menü, sonradan üretilen sürümü göstermemeli.'
        );
    }

    // --- MEDIA-USAGE-ON-PUBLISH-01 ----------------------------------------

    public function test_publishing_records_the_usage_so_the_image_cannot_be_deleted_out_from_under_it(): void
    {
        $mediaId = $this->uploadImage('itemImage');
        $this->api()->putJson("/api/workspaces/{$this->workspaceId}/menu-items/{$this->menuItemId}/image",
            ['mediaAssetId' => $mediaId])->assertOk();

        $publicationId = (int) $this->publish()['id'];

        self::assertSame(1, DB::table('media_usages')
            ->where('media_asset_id', $mediaId)->where('publication_id', $publicationId)->count(),
            'MEDIA-USAGE-ON-PUBLISH-01: yayın, görselin kullanıldığını kayda geçirmeli.');

        // Ve `docs/76`'daki koruma artık GERÇEK bir yol üzerinde çalışıyor.
        $this->api()->deleteJson("/api/workspaces/{$this->workspaceId}/media/{$mediaId}")
            ->assertStatus(409);
    }

    // --- BRAND-LOGO-ON-MENU-01 --------------------------------------------

    public function test_the_brand_logo_reaches_the_guest_header(): void
    {
        $logoId = $this->uploadImage('logo', 600);

        $this->api()->putJson("/api/workspaces/{$this->workspaceId}/brand/logo",
            ['mediaAssetId' => $logoId])->assertOk();

        $snapshot = $this->publish()['snapshot'];
        $logo = $snapshot['identity']['logo'] ?? null;

        self::assertIsArray($logo, 'BRAND-LOGO-ON-MENU-01: kimlik logoyu taşımalı.');
        self::assertNotEmpty($logo['sources']);
        self::assertNotEmpty($logo['altText'], 'Logo alt metni boş olamaz; ekran okuyucu "resim" der geçer.');

        $html = view('public-menu', ['snapshot' => $snapshot])->render();
        self::assertMatchesRegularExpression('#<img[^>]+class="qr-menu-logo"#', $html);
    }

    public function test_a_menu_without_a_logo_still_renders_its_header(): void
    {
        $html = view('public-menu', ['snapshot' => $this->publish()['snapshot']])->render();

        self::assertStringContainsString('Zeytin Restoranları', $html);
        self::assertDoesNotMatchRegularExpression('#<img[^>]+class="qr-menu-logo"#', $html);
    }
}
