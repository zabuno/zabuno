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
 * Requirement IDs: MEDIA-SCANNER-HONEST-01, MEDIA-DELETE-IMPACT-01,
 * MEDIA-DELETE-UNUSED-OK-01.
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
