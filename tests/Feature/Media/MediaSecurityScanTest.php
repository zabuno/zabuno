<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\Dto\MediaScanResult;
use App\Application\Media\Dto\MediaScanVerdict;
use App\Application\Media\Port\MalwareScannerPort;
use App\Application\Media\UseCase\ScanQuarantinedMediaAsset;
use App\Domain\Media\MediaAssetStatus;
use App\Infrastructure\Media\Persistence\EloquentMediaRepository;
use App\Infrastructure\Media\Scanning\ClamavMalwareScanner;
use App\Infrastructure\Media\Scanning\UnavailableMalwareScanner;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Blind targeted RED for the media accepted-state package (this package's
 * frozen scope: the first clean post-scan state is `accepted`
 * (MediaAssetStatus::Accepted, value "accepted") per docs/07 and
 * modules/core-file-media.md; a Clean ClamAV verdict atomically moves an
 * asset from Scanning to Accepted without touching its bytes/key; an
 * Infected verdict moves an asset to Rejected, never Accepted; `Processing`
 * and `Ready` remain unintroduced).
 *
 * MediaAssetStatus::Accepted does not exist yet, and the real ClamAV
 * integration path still leaves a Clean-scanned asset in `scanning` instead
 * of moving it to `accepted`, so the enum test and the ClamAV-clean test
 * below are expected to fail RED. The new Infected-verdict guard may
 * already pass against the existing Rejected transition.
 *
 * Requirement IDs: MEDIA-SCAN-STATUS-ENUM-01, MEDIA-SCAN-ADAPTER-INDETERMINATE-01,
 * MEDIA-SCAN-TRANSITION-01, MEDIA-SCAN-REJECTED-NO-REQUEUE-01, MEDIA-SCAN-UPLOAD-HONEST-01,
 * MEDIA-SCAN-CLAMAV-REAL-PATH-01, MEDIA-ACCEPT-CLEAN-01, MEDIA-ACCEPT-INFECTED-01.
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

    public function test_scanning_and_accepted_status_round_trip_and_no_processing_ready_case_is_introduced(): void
    {
        self::assertSame(
            'scanning',
            MediaAssetStatus::Scanning->value,
            'MEDIA-SCAN-STATUS-ENUM-01: Scanning enum case\'i "scanning" değerine sahip olmalı.'
        );

        self::assertSame(
            'accepted',
            MediaAssetStatus::Accepted->value,
            'MEDIA-ACCEPT-CLEAN-01: Accepted enum case\'i "accepted" değerine sahip olmalı (docs/07, modules/core-file-media.md).'
        );

        $caseNames = array_map(static fn (MediaAssetStatus $case): string => $case->name, MediaAssetStatus::cases());

        foreach (['Processing', 'Ready'] as $forbidden) {
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
        $quarantinedBytes = 'fake-image-bytes';
        Storage::disk('local')->put($diskPath, $quarantinedBytes);
        $expectedAbsolutePath = Storage::disk('local')->path($diskPath);

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
        $spyScanner = new class($capturedPath) implements MalwareScannerPort
        {
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
            $expectedAbsolutePath,
            $capturedPath,
            'MEDIA-SCAN-TRANSITION-01: tarama, local disk adaptörünün gerçek filesystem\'de var olan mutlak yoluna karşı çalışmalı, tenant-private relative anahtara değil.'
        );

        self::assertTrue(
            is_string($capturedPath) && is_file($capturedPath),
            'MEDIA-SCAN-TRANSITION-01: scanner\'a verilen yol, gerçek karantina byte\'larının bulunduğu var olan bir dosya olmalı.'
        );

        self::assertSame(
            $quarantinedBytes,
            is_string($capturedPath) ? file_get_contents($capturedPath) : null,
            'MEDIA-SCAN-TRANSITION-01: scanner\'a verilen dosya, karantinaya alınmış tam byte\'ları içermeli.'
        );

        $asset->refresh();

        self::assertSame(
            $diskPath,
            $asset->disk_path,
            'MEDIA-SCAN-TRANSITION-01: media_assets.disk_path taramadan sonra da orijinal tenant-scoped relative anahtar olarak kalmalı.'
        );

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
        $spyScanner = new class($scannerCalled) implements MalwareScannerPort
        {
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

    // --- MEDIA-SCAN-CLAMAV-REAL-PATH-01 / MEDIA-ACCEPT-CLEAN-01 --------------

    public function test_real_clamav_scanner_reads_actual_quarantined_bytes_via_safe_argv_and_clean_verdict_atomically_moves_asset_to_accepted(): void
    {
        Storage::fake('local');
        $workspaceId = $this->persistedWorkspaceId('zeytin-media-scan-clamav-real');

        $diskPath = "quarantine/{$workspaceId}/clamav-integration-asset";
        $quarantinedBytes = 'real-quarantined-bytes-for-clamav-fake-executable';
        Storage::disk('local')->put($diskPath, $quarantinedBytes);

        $asset = MediaAsset::query()->create([
            'workspace_id' => $workspaceId,
            'disk_path' => $diskPath,
            'original_name' => 'logo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => strlen($quarantinedBytes),
            'alt_text' => 'Zeytin Restoranı logosu',
            'slot' => 'menu',
            'status' => MediaAssetStatus::Quarantined->value,
        ]);

        $evidenceFile = tempnam(sys_get_temp_dir(), 'clamav-evidence-');
        self::assertNotFalse($evidenceFile);
        unlink($evidenceFile);

        $fakeBinary = tempnam(sys_get_temp_dir(), 'fake-clamav-');
        self::assertNotFalse($fakeBinary);

        $script = "#!/bin/sh\ncp -- \"\$2\" ".escapeshellarg($evidenceFile)."\nexit 0\n";
        file_put_contents($fakeBinary, $script);
        chmod($fakeBinary, 0755);

        try {
            $scanner = new ClamavMalwareScanner($fakeBinary, 5.0);
            $useCase = new ScanQuarantinedMediaAsset($scanner, new EloquentMediaRepository);

            $useCase->__invoke($workspaceId, (int) $asset->getKey());

            self::assertFileExists(
                $evidenceFile,
                'MEDIA-SCAN-CLAMAV-REAL-PATH-01: sahte clamav yürütülebilir dosyası, safe argv üzerinden verilen yola erişebilmeli.'
            );

            self::assertSame(
                $quarantinedBytes,
                file_get_contents($evidenceFile),
                'MEDIA-SCAN-CLAMAV-REAL-PATH-01: process, karantinaya alınmış gerçek byte\'ları okumalı.'
            );

            $asset->refresh();

            self::assertSame(
                MediaAssetStatus::Accepted->value,
                $asset->status,
                'MEDIA-ACCEPT-CLEAN-01: clean sonuç asset\'i atomik olarak scanning\'den accepted\'a taşımalı.'
            );

            self::assertSame(
                $diskPath,
                $asset->disk_path,
                'MEDIA-ACCEPT-CLEAN-01: clean kabul, orijinal tenant-scoped disk_path anahtarını değiştirmemeli.'
            );

            self::assertSame(
                $quarantinedBytes,
                Storage::disk('local')->get($diskPath),
                'MEDIA-ACCEPT-CLEAN-01: clean kabul, gerçek karantina byte\'larını değiştirmemeli.'
            );
        } finally {
            if (is_file($fakeBinary)) {
                unlink($fakeBinary);
            }
            if (is_file($evidenceFile)) {
                unlink($evidenceFile);
            }
        }
    }

    // --- MEDIA-ACCEPT-INFECTED-01 ---------------------------------------------

    public function test_infected_verdict_moves_same_workspace_asset_to_rejected_and_never_accepted(): void
    {
        Storage::fake('local');
        $workspaceId = $this->persistedWorkspaceId('zeytin-media-scan-infected');

        $diskPath = "quarantine/{$workspaceId}/infected-asset";
        $quarantinedBytes = 'fake-infected-bytes';
        Storage::disk('local')->put($diskPath, $quarantinedBytes);

        $asset = MediaAsset::query()->create([
            'workspace_id' => $workspaceId,
            'disk_path' => $diskPath,
            'original_name' => 'logo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => strlen($quarantinedBytes),
            'alt_text' => 'Zeytin Restoranı logosu',
            'slot' => 'menu',
            'status' => MediaAssetStatus::Quarantined->value,
        ]);

        $infectedScanner = new class implements MalwareScannerPort
        {
            public function scan(string $diskPath): MediaScanResult
            {
                return new MediaScanResult(MediaScanVerdict::Infected);
            }
        };

        $useCase = new ScanQuarantinedMediaAsset($infectedScanner, new EloquentMediaRepository);
        $useCase->__invoke($workspaceId, (int) $asset->getKey());

        $asset->refresh();

        self::assertSame(
            MediaAssetStatus::Rejected->value,
            $asset->status,
            'MEDIA-ACCEPT-INFECTED-01: infected verdict aynı workspace\'in asset\'ini rejected\'a taşımalı.'
        );

        self::assertNotSame(
            MediaAssetStatus::Accepted->value,
            $asset->status,
            'MEDIA-ACCEPT-INFECTED-01: infected verdict asset\'i asla accepted\'a taşımamalı.'
        );
    }

    // --- MEDIA-SCAN-CROSS-WORKSPACE-NOOP-01 ----------------------------------

    public function test_cross_workspace_claim_never_resolves_path_and_never_invokes_scanner(): void
    {
        Storage::fake('local');
        $workspaceId = $this->persistedWorkspaceId('zeytin-media-scan-cross-ws-owner');
        $otherWorkspaceId = $this->persistedWorkspaceId('zeytin-media-scan-cross-ws-attacker');

        $diskPath = "quarantine/{$workspaceId}/cross-workspace-asset";
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

        $scannerCalled = false;
        $spyScanner = new class($scannerCalled) implements MalwareScannerPort
        {
            public function __construct(private bool &$scannerCalled) {}

            public function scan(string $diskPath): MediaScanResult
            {
                $this->scannerCalled = true;

                return (new UnavailableMalwareScanner)->scan($diskPath);
            }
        };

        $useCase = new ScanQuarantinedMediaAsset($spyScanner, new EloquentMediaRepository);
        $useCase->__invoke($otherWorkspaceId, (int) $asset->getKey());

        self::assertFalse(
            $scannerCalled,
            'MEDIA-SCAN-CROSS-WORKSPACE-NOOP-01: farklı bir workspace\'in id\'siyle yapılan claim scanner\'ı asla çağırmamalı.'
        );

        $asset->refresh();

        self::assertSame(
            MediaAssetStatus::Quarantined->value,
            $asset->status,
            'MEDIA-SCAN-CROSS-WORKSPACE-NOOP-01: cross-workspace claim asset\'i scanning\'e terfi ettirmemeli.'
        );

        self::assertSame(
            $diskPath,
            $asset->disk_path,
            'MEDIA-SCAN-CROSS-WORKSPACE-NOOP-01: cross-workspace claim disk_path\'i hiç değiştirmemeli/çözümlememeli.'
        );
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
