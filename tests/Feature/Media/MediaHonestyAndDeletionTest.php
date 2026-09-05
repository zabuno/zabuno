<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\Dto\MediaScanResult;
use App\Application\Media\Dto\MediaScanVerdict;
use App\Application\Media\Port\MalwareScannerPort;
use App\Domain\Media\MediaAssetStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P0-08 RED — dürüstlük ve silme etkisi (`docs/76`).
 *
 * KRİTER 3. Virüs tarayıcı devre dışıysa ürün bunu kullanıcıya karşı
 * "tarandı" gibi göstermez. Bugün dosya `scanning` durumunda sessizce
 * bekliyor ve sahip hiçbir yerde tarayıcının çalışmadığını okumuyor —
 * yani ürün, yapmadığı bir şeyi yapıyormuş gibi duruyor.
 *
 * KRİTER 4. Bir görsel silindiğinde onu kullanan YAYINLANMIŞ menü
 * kırılmaz. Yayın, sahibin onayladığı donmuş hâldir; panelden yapılan bir
 * temizlik onu misafirin gözü önünde bozamaz.
 *
 * KRİTER 3'ÜN İKİNCİ YARISI (FF-150). "Kayda geçiyor" ile "sahip okuyor"
 * aynı şey değildir. Sebep bugün YALNIZ kütüphane listesinde görünüyordu;
 * sahip dosyayı YÜKLEDİĞİ ekranda "Media upload complete." okuyup ayrılıyor
 * ve bir daha bakmıyor. Sessizce bekleyen dosya, ürünün verebileceği en
 * kötü cevaptır: sahip yanlış bir şey yaptığını sanır, tekrar dener, sonra
 * ürünün bozuk olduğunu düşünür.
 *
 * Aynı sessizlik menüye bağlarken de vardı: ret "İşlenmesi bitince yeniden
 * deneyin" diyordu. Tarayıcı bu ortamda hiç yokken işleme ASLA bitmez —
 * yani ürün olmayacak bir şeyi vaat ediyordu.
 *
 * Requirement IDs: MEDIA-SCANNER-HONEST-01, MEDIA-DELETE-IMPACT-01,
 * MEDIA-DELETE-UNUSED-OK-01, MEDIA-SCANNER-HONEST-AT-UPLOAD-01,
 * MEDIA-BIND-HELD-HONEST-01.
 */
final class MediaHonestyAndDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function ownerAndWorkspace(string $slug): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => $slug, 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId, 'user_id' => $owner->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$owner, $workspaceId];
    }

    // --- MEDIA-SCANNER-HONEST-01 ------------------------------------------

    public function test_when_no_scanner_is_available_the_product_records_that_the_file_was_not_scanned(): void
    {
        Storage::fake('local');
        $this->app->instance(MalwareScannerPort::class, new class implements MalwareScannerPort
        {
            public function scan(string $diskPath): MediaScanResult
            {
                return new MediaScanResult(MediaScanVerdict::Indeterminate);
            }
        });

        [$owner, $workspaceId] = $this->ownerAndWorkspace('scanner-honest');

        $response = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$workspaceId}/media",
            ['file' => UploadedFile::fake()->image('kebap.jpg', 400, 400), 'altText' => 'Adana kebap', 'slot' => 'itemImage']
        );

        $response->assertStatus(201);
        $mediaId = (int) $response->json('id');

        // Dosya kabul edilmiş SAYILMAZ: taranmadı.
        self::assertSame(
            MediaAssetStatus::Scanning->value,
            DB::table('media_assets')->where('id', $mediaId)->value('status'),
            'MEDIA-SCANNER-HONEST-01: taranamayan dosya kabul edilmiş gibi ilerletilemez.'
        );

        // Ve ürün NEDENİNİ kaydeder: sahip "taranıyor" ile "taranamıyor"
        // arasındaki farkı görebilmeli.
        $job = DB::table('media_processing_jobs')
            ->where('media_asset_id', $mediaId)->where('kind', 'scan')->orderByDesc('id')->first();

        self::assertNotNull($job, 'MEDIA-SCANNER-HONEST-01: tarama denemesi bir iz bırakmalı.');
        self::assertSame('held', (string) $job->state);
        self::assertNotEmpty((string) $job->failure_reason);

        // Sahibin gördüğü listede de bu sebep var.
        $listed = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$workspaceId}/media")->json();

        $row = collect($listed['data'] ?? $listed)->firstWhere('id', $mediaId);
        self::assertNotEmpty(
            $row['statusReason'] ?? '',
            'MEDIA-SCANNER-HONEST-01: sahip ekranda neden beklediğini okuyabilmeli.'
        );
    }

    // --- MEDIA-SCANNER-HONEST-AT-UPLOAD-01 --------------------------------

    public function test_the_upload_answer_itself_carries_the_reason_the_file_is_waiting(): void
    {
        Storage::fake('local');
        $this->app->instance(MalwareScannerPort::class, new class implements MalwareScannerPort
        {
            public function scan(string $diskPath): MediaScanResult
            {
                return new MediaScanResult(MediaScanVerdict::Indeterminate);
            }
        });

        [$owner, $workspaceId] = $this->ownerAndWorkspace('scanner-at-upload');

        $response = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$workspaceId}/media",
            ['file' => UploadedFile::fake()->image('kebap.jpg', 400, 400), 'altText' => 'Adana kebap', 'slot' => 'itemImage']
        );

        $response->assertStatus(201);

        /*
            Sebep YÜKLEMENİN KENDİ CEVABINDA olmalı. Kütüphane listesine
            koymak yetmiyor: sahip yükleme ekranından ayrılmadan önce
            dosyasının beklediğini öğrenmeli, sonraki sekmede değil.
        */
        self::assertNotEmpty(
            (string) $response->json('statusReason'),
            'MEDIA-SCANNER-HONEST-AT-UPLOAD-01: sahip, yüklediği YERDE neden beklediğini okumalı.'
        );

        // Ve cevap taranmış gibi davranmıyor: durum hâlâ ilerlememiş.
        self::assertSame(MediaAssetStatus::Scanning->value, $response->json('status'));
    }

    // --- MEDIA-BIND-HELD-HONEST-01 ----------------------------------------

    public function test_binding_a_held_image_to_a_menu_item_does_not_promise_that_processing_will_finish(): void
    {
        Storage::fake('local');
        $this->app->instance(MalwareScannerPort::class, new class implements MalwareScannerPort
        {
            public function scan(string $diskPath): MediaScanResult
            {
                return new MediaScanResult(MediaScanVerdict::Indeterminate);
            }
        });

        [$owner, $workspaceId] = $this->ownerAndWorkspace('bind-held-honest');
        $menuItemId = $this->menuItemIn($workspaceId, 'bind-held-honest');

        $mediaId = (int) $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$workspaceId}/media",
            ['file' => UploadedFile::fake()->image('kebap.jpg', 400, 400), 'altText' => 'Adana kebap', 'slot' => 'itemImage']
        )->json('id');

        $response = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->putJson(
                "/api/workspaces/{$workspaceId}/menu-items/{$menuItemId}/image",
                ['mediaAssetId' => $mediaId],
            );

        // Güvenlik sınırı DEĞİŞMEZ: taranmamış görsel menüye bağlanmaz.
        $response->assertStatus(422);
        self::assertSame(
            0,
            DB::table('media_usages')->where('media_asset_id', $mediaId)->count(),
            'MEDIA-BIND-HELD-HONEST-01: taranmamış görsel yine de bağlanmamalı.'
        );

        $message = (string) $response->json('message');

        /*
            Ret, olmayacak bir şeyi VAAT ETMEZ. Bu ortamda tarayıcı hiç
            yok; "işlenmesi bitince" diye beklenecek bir an yok. Sahip
            saatlerce yenilemesin diye sebep, kaydedilmiş gerçek sebeptir.
        */
        self::assertStringNotContainsString('İşlenmesi bitince', $message);
        self::assertSame(
            (string) DB::table('media_processing_jobs')
                ->where('media_asset_id', $mediaId)->where('kind', 'scan')
                ->orderByDesc('id')->value('failure_reason'),
            $message,
            'MEDIA-BIND-HELD-HONEST-01: ret, kayda geçen gerçek sebebi söylemeli.'
        );
    }

    /**
     * Menüye bağlama testinin gerektirdiği en küçük gerçek zincir:
     * marka → şube → menü → kategori → ürün → menü satırı.
     *
     * Satırlar elle yazılıyor çünkü bu test menü KURMAYI değil, kurulmuş
     * bir menüye taranmamış bir görseli bağlamayı ölçüyor.
     */
    private function menuItemIn(int $workspaceId, string $slug): int
    {
        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Zeytin Restoranları',
            'slug' => "brand-{$slug}", 'locale' => 'tr',
            'timezone' => 'Europe/Istanbul', 'currency' => 'TRY',
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
            'public_key' => "pk-{$slug}", 'workspace_id' => $workspaceId,
            'location_id' => $locationId, 'name' => 'Ana Menü', 'state' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $categoryId = (int) DB::table('menu_categories')->insertGetId([
            'menu_id' => $menuId, 'name' => 'Kebaplar', 'position' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $productId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Adana Kebap',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) DB::table('menu_items')->insertGetId([
            'category_id' => $categoryId, 'product_id' => $productId,
            'price_minor_amount' => 38000, 'currency_code' => 'TRY',
            'position' => 0, 'is_visible' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_clean_scan_leaves_no_misleading_reason(): void
    {
        Storage::fake('local');
        $this->app->instance(MalwareScannerPort::class, new class implements MalwareScannerPort
        {
            public function scan(string $diskPath): MediaScanResult
            {
                return new MediaScanResult(MediaScanVerdict::Clean);
            }
        });

        [$owner, $workspaceId] = $this->ownerAndWorkspace('scanner-clean');

        $mediaId = (int) $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$workspaceId}/media",
            ['file' => UploadedFile::fake()->image('kebap.jpg', 400, 400), 'altText' => 'Adana kebap', 'slot' => 'itemImage']
        )->json('id');

        $listed = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/workspaces/{$workspaceId}/media")->json();

        $row = collect($listed['data'] ?? $listed)->firstWhere('id', $mediaId);

        self::assertSame(MediaAssetStatus::Ready->value, $row['status'] ?? null);
        self::assertNull($row['statusReason'] ?? null, 'Sorunsuz bir dosyaya sebep yazmak gürültüdür.');
    }

    // --- MEDIA-DELETE-IMPACT-01 -------------------------------------------

    public function test_deleting_an_image_used_by_a_published_menu_is_refused_with_an_explanation(): void
    {
        Storage::fake('local');
        $this->app->instance(MalwareScannerPort::class, new class implements MalwareScannerPort
        {
            public function scan(string $diskPath): MediaScanResult
            {
                return new MediaScanResult(MediaScanVerdict::Clean);
            }
        });

        [$owner, $workspaceId] = $this->ownerAndWorkspace('delete-impact');

        $mediaId = (int) $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$workspaceId}/media",
            ['file' => UploadedFile::fake()->image('kebap.jpg', 400, 400), 'altText' => 'Adana kebap', 'slot' => 'itemImage']
        )->json('id');

        // Görsel YAYINLANMIŞ bir menüde kullanılıyor.
        DB::table('media_usages')->insert([
            'workspace_id' => $workspaceId,
            'media_asset_id' => $mediaId,
            'entity_type' => 'menu_item',
            'entity_id' => 1,
            'slot' => 'itemImage',
            'publication_id' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->deleteJson("/api/workspaces/{$workspaceId}/media/{$mediaId}");

        $response->assertStatus(409);
        self::assertNotEmpty(
            $response->json('message'),
            'MEDIA-DELETE-IMPACT-01: ret sessiz olamaz; sahip NEDEN silinemediğini okumalı.'
        );

        self::assertNotNull(
            DB::table('media_assets')->where('id', $mediaId)->first(),
            'MEDIA-DELETE-IMPACT-01: yayınlanmış menüdeki görsel silinmemeli.'
        );
    }

    // --- MEDIA-DELETE-UNUSED-OK-01 ----------------------------------------

    public function test_deleting_an_unused_image_still_works(): void
    {
        Storage::fake('local');
        $this->app->instance(MalwareScannerPort::class, new class implements MalwareScannerPort
        {
            public function scan(string $diskPath): MediaScanResult
            {
                return new MediaScanResult(MediaScanVerdict::Clean);
            }
        });

        [$owner, $workspaceId] = $this->ownerAndWorkspace('delete-unused');

        $mediaId = (int) $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$workspaceId}/media",
            ['file' => UploadedFile::fake()->image('kebap.jpg', 400, 400), 'altText' => 'Adana kebap', 'slot' => 'itemImage']
        )->json('id');

        $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->deleteJson("/api/workspaces/{$workspaceId}/media/{$mediaId}")
            ->assertStatus(204);

        // Yumuşak silme: satır tarihçe için kalır, kullanımdan kalkar.
        self::assertNotNull(
            DB::table('media_assets')->where('id', $mediaId)->value('deleted_at'),
            'MEDIA-DELETE-UNUSED-OK-01: kullanılmayan görsel silinebilmeli.'
        );
    }
}
