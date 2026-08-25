<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\Dto\MediaScanResult;
use App\Application\Media\Port\MalwareScannerPort;
use App\Application\Media\UseCase\ScanQuarantinedMediaAsset;
use App\Domain\Media\MediaAssetStatus;
use App\Infrastructure\Media\Persistence\EloquentMediaRepository;
use App\Infrastructure\Media\Scanning\UnavailableMalwareScanner;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Blind targeted RED for the media security-scan seam (this package's frozen
 * scope: a fail-closed scan step after intake, a visible "scanning" rung, and
 * an unavailable/indeterminate adapter only — no ClamAV, no accepted/
 * processing/ready state, no public URL/decode/derivative).
 *
 * None of App\Domain\Media\MediaAssetStatus::Scanning,
 * App\Infrastructure\Media\Scanning\UnavailableMalwareScanner, or
 * App\Application\Media\UseCase\ScanQuarantinedMediaAsset exist yet, so every
 * test below is expected to fail RED with a class/case-not-found error, not a
 * logic assertion failure or a bootstrap defect in this suite.
 *
 * Requirement IDs: MEDIA-SCAN-STATUS-ENUM-01, MEDIA-SCAN-ADAPTER-INDETERMINATE-01,
 * MEDIA-SCAN-TRANSITION-01, MEDIA-SCAN-REJECTED-NO-REQUEUE-01, MEDIA-SCAN-UPLOAD-HONEST-01.
 */
final class MediaSecurityScanTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function ownerWorkspace(User $owner, string $slugSeed): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin Restoranları',
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

        return $workspaceId;
    }

    // --- MEDIA-SCAN-STATUS-ENUM-01 ------------------------------------------

    public function test_scanning_status_round_trips_and_no_accepted_processing_ready_case_is_introduced(): void
    {
        self::assertSame(
            'scanning',
            MediaAssetStatus::Scanning->value,
            'MEDIA-SCAN-STATUS-ENUM-01: Scanning enum case\'i "scanning" değerine sahip olmalı.'
        );

        $caseNames = array_map(static fn (MediaAssetStatus $case): string => $case->name, MediaAssetStatus::cases());

        foreach (['Accepted', 'Processing', 'Ready'] as $forbidden) {
            self::assertNotContains(
                $forbidden,
                $caseNames,
                "MEDIA-SCAN-STATUS-ENUM-01: MediaAssetStatus içinde {$forbidden} case'i tanımlanmamalı."
            );
        }
    }

    // --- MEDIA-SCAN-ADAPTER-INDETERMINATE-01 --------------------------------

    public function test_unavailable_malware_scanner_returns_indeterminate_never_clean_or_infected(): void
    {
        self::assertTrue(
            is_subclass_of(UnavailableMalwareScanner::class, MalwareScannerPort::class)
                || in_array(MalwareScannerPort::class, class_implements(UnavailableMalwareScanner::class) ?: [], true),
            'MEDIA-SCAN-ADAPTER-INDETERMINATE-01: UnavailableMalwareScanner, MalwareScannerPort sözleşmesini uygulamalı.'
        );

        $scanner = new UnavailableMalwareScanner;
        $result = $scanner->scan('quarantine/1/does-not-matter');

        self::assertInstanceOf(
            MediaScanResult::class,
            $result,
            'MEDIA-SCAN-ADAPTER-INDETERMINATE-01: scan() bir MediaScanResult DTO\'su döndürmeli.'
        );

        self::assertSame(
            'indeterminate',
            $result->verdict->value,
            'MEDIA-SCAN-ADAPTER-INDETERMINATE-01: UnavailableMalwareScanner yalnız indeterminate döndürmeli, asla clean/infected değil.'
        );
    }

    // --- MEDIA-SCAN-TRANSITION-01 --------------------------------------------

    public function test_scan_quarantined_media_asset_scans_exact_stored_path_and_transitions_to_scanning_with_no_public_output(): void
    {
        Storage::fake('local');
        $workspaceId = $this->persistedWorkspaceId('zeytin-media-scan-transition');

        $diskPath = "quarantine/{$workspaceId}/existing-asset";
        Storage::disk('local')->put($diskPath, 'fake-image-bytes');

        $asset = MediaAsset::query()->create([
            'workspace_id' => $workspaceId,
            'disk_path' => $diskPath,
            'original_name' => 'logo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 17,
            'alt_text' => 'Zeytin Restoranı logosu',
            'slot' => 'menu',
            'status' => MediaAssetStatus::Quarantined->value,
        ]);

        $capturedPath = null;
        $spyScanner = new class($capturedPath) implements MalwareScannerPort {
            public function __construct(private mixed &$capturedPath) {}

            public function scan(string $diskPath): MediaScanResult
            {
                $this->capturedPath = $diskPath;

                return (new UnavailableMalwareScanner)->scan($diskPath);
            }
        };

        $useCase = new ScanQuarantinedMediaAsset($spyScanner, new EloquentMediaRepository);
        $useCase->__invoke($workspaceId, (int) $asset->getKey());

        self::assertSame(
            $diskPath,
            $capturedPath,
            'MEDIA-SCAN-TRANSITION-01: tarama, tam olarak tenant-private stored disk_path üzerinde çalışmalı.'
        );

        $asset->refresh();

        self::assertSame(
            'scanning',
            $asset->status,
            'MEDIA-SCAN-TRANSITION-01: indeterminate sonucunda asset scanning durumunda kalmalı.'
        );

        self::assertNotSame('accepted', $asset->status);
        self::assertNotSame('processing', $asset->status);
        self::assertNotSame('ready', $asset->status);
    }

    // --- MEDIA-SCAN-REJECTED-NO-REQUEUE-01 -----------------------------------

    public function test_rejected_asset_cannot_be_requeued_for_scanning_and_scanner_is_not_called(): void
    {
        Storage::fake('local');
        $workspaceId = $this->persistedWorkspaceId('zeytin-media-scan-no-requeue');

        $diskPath = "quarantine/{$workspaceId}/rejected-asset";
        Storage::disk('local')->put($diskPath, 'fake-image-bytes');

        $asset = MediaAsset::query()->create([
            'workspace_id' => $workspaceId,
            'disk_path' => $diskPath,
            'original_name' => 'logo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 17,
            'alt_text' => 'Reddedilen logo',
            'slot' => 'menu',
            'status' => MediaAssetStatus::Rejected->value,
        ]);

        $scannerCalled = false;
        $spyScanner = new class($scannerCalled) implements MalwareScannerPort {
            public function __construct(private bool &$scannerCalled) {}

            public function scan(string $diskPath): MediaScanResult
            {
                $this->scannerCalled = true;

                return (new UnavailableMalwareScanner)->scan($diskPath);
            }
        };

        $useCase = new ScanQuarantinedMediaAsset($spyScanner, new EloquentMediaRepository);
        $useCase->__invoke($workspaceId, (int) $asset->getKey());

        self::assertFalse(
            $scannerCalled,
            'MEDIA-SCAN-REJECTED-NO-REQUEUE-01: rejected/non-quarantined varlık için scanner çağrılmamalı.'
        );

        $asset->refresh();
        self::assertSame(
            MediaAssetStatus::Rejected->value,
            $asset->status,
            'MEDIA-SCAN-REJECTED-NO-REQUEUE-01: rejected varlık scanning\'e terfi ettirilmemeli.'
        );
    }

    // --- MEDIA-SCAN-UPLOAD-HONEST-01 -----------------------------------------

    public function test_real_owner_upload_with_unavailable_adapter_returns_honest_scanning_status_and_persists_private_bytes(): void
    {
        Storage::fake('local');
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'zeytin-media-scan-upload');

        $file = UploadedFile::fake()->image('logo.jpg', 200, 200)->size(50);

        $response = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$workspaceId}/media",
            ['file' => $file, 'altText' => 'Zeytin Restoranı logosu', 'slot' => 'menu']
        );

        $response->assertStatus(201);

        $status = $response->json('status');
        self::assertSame(
            'scanning',
            $status,
            'MEDIA-SCAN-UPLOAD-HONEST-01: gerçek upload sonrası, indeterminate adapter ile status dürüstçe scanning olmalı.'
        );
        self::assertNotSame('accepted', $status);
        self::assertNotSame('processing', $status);
        self::assertNotSame('ready', $status);

        $mediaId = (int) $response->json('id');
        $diskPath = DB::table('media_assets')->where('id', $mediaId)->value('disk_path');
        self::assertNotEmpty($diskPath, 'MEDIA-SCAN-UPLOAD-HONEST-01: private quarantine bytes disk_path ile kalıcı olmalı.');
        Storage::disk('local')->assertExists($diskPath);

        self::assertArrayNotHasKey('url', $response->json() ?? [], 'MEDIA-SCAN-UPLOAD-HONEST-01: yanıt public url içermemeli.');
        self::assertArrayNotHasKey('publicUrl', $response->json() ?? [], 'MEDIA-SCAN-UPLOAD-HONEST-01: yanıt public url içermemeli.');
        self::assertArrayNotHasKey('derivatives', $response->json() ?? [], 'MEDIA-SCAN-UPLOAD-HONEST-01: yanıt derivative içermemeli.');
    }

    private function persistedWorkspaceId(string $slugSeed): int
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        return (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin Restoranları',
            'slug' => $slugSeed,
            'state' => 'active',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
