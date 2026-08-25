<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\Dto\ProcessableMediaAsset;
use App\Domain\Media\MediaAssetStatus;
use App\Infrastructure\Media\Persistence\EloquentMediaRepository;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Blind targeted RED for the media processing-claim package (frozen scope:
 * only an exact same-workspace asset currently in `accepted` can be
 * atomically claimed into `processing`, returning a processing-specific
 * ProcessableMediaAsset DTO with id, workspaceId and the storage adapter's
 * absolute original path; non-accepted states and cross-workspace/second
 * claims are no-ops; disk_path/bytes stay unchanged).
 *
 * ProcessableMediaAsset and the repository's claim-into-processing method
 * do not exist yet, so all three tests below are expected to fail RED.
 *
 * Requirement IDs: MEDIA-PROCESS-CLAIM-ACCEPTED-01, MEDIA-PROCESS-CLAIM-NONACCEPTED-NOOP-01,
 * MEDIA-PROCESS-CLAIM-CROSS-WORKSPACE-AND-SECOND-CLAIM-NOOP-01.
 */
final class MediaProcessingClaimTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepted_same_workspace_asset_is_atomically_claimed_into_processing_with_absolute_path_and_unchanged_key_and_bytes(): void
    {
        Storage::fake('local');
        $workspaceId = $this->persistedWorkspaceId('zeytin-media-process-claim-accepted');

        $diskPath = "quarantine/{$workspaceId}/accepted-asset";
        $bytes = 'accepted-clean-bytes';
        Storage::disk('local')->put($diskPath, $bytes);
        $expectedAbsolutePath = Storage::disk('local')->path($diskPath);

        $asset = MediaAsset::query()->create([
            'workspace_id' => $workspaceId,
            'disk_path' => $diskPath,
            'original_name' => 'logo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => strlen($bytes),
            'alt_text' => 'Zeytin Restoranı logosu',
            'slot' => 'menu',
            'status' => MediaAssetStatus::Accepted->value,
        ]);

        $repository = new EloquentMediaRepository;
        $claimed = $repository->claimAcceptedForProcessing($workspaceId, (int) $asset->getKey());

        self::assertInstanceOf(
            ProcessableMediaAsset::class,
            $claimed,
            'MEDIA-PROCESS-CLAIM-ACCEPTED-01: accepted aynı-workspace claim bir ProcessableMediaAsset DTO\'su döndürmeli.'
        );

        self::assertSame((int) $asset->getKey(), $claimed->id);
        self::assertSame($workspaceId, $claimed->workspaceId);
        self::assertSame(
            $expectedAbsolutePath,
            $claimed->diskPath,
            'MEDIA-PROCESS-CLAIM-ACCEPTED-01: DTO, storage adaptörünün gerçek filesystem\'deki mutlak orijinal yolunu taşımalı.'
        );

        $asset->refresh();

        self::assertSame(
            MediaAssetStatus::Processing->value,
            $asset->status,
            'MEDIA-PROCESS-CLAIM-ACCEPTED-01: claim, asset\'i atomik olarak accepted\'dan processing\'e taşımalı.'
        );

        self::assertSame(
            $diskPath,
            $asset->disk_path,
            'MEDIA-PROCESS-CLAIM-ACCEPTED-01: claim, orijinal tenant-scoped disk_path anahtarını değiştirmemeli.'
        );

        self::assertSame(
            $bytes,
            Storage::disk('local')->get($diskPath),
            'MEDIA-PROCESS-CLAIM-ACCEPTED-01: claim, gerçek dosya byte\'larını değiştirmemeli.'
        );
    }

    /**
     * @return list<string>
     */
    public static function nonAcceptedStatusProvider(): array
    {
        return [
            'quarantined' => [MediaAssetStatus::Quarantined->value],
            'scanning' => [MediaAssetStatus::Scanning->value],
            'rejected' => [MediaAssetStatus::Rejected->value],
        ];
    }

    #[DataProvider('nonAcceptedStatusProvider')]
    public function test_non_accepted_states_are_a_no_op_and_never_reach_processing(string $status): void
    {
        Storage::fake('local');
        $workspaceId = $this->persistedWorkspaceId('zeytin-media-process-claim-non-accepted');

        $diskPath = "quarantine/{$workspaceId}/non-accepted-asset-{$status}";
        Storage::disk('local')->put($diskPath, 'fake-bytes');

        $asset = MediaAsset::query()->create([
            'workspace_id' => $workspaceId,
            'disk_path' => $diskPath,
            'original_name' => 'logo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 11,
            'alt_text' => 'Zeytin Restoranı logosu',
            'slot' => 'menu',
            'status' => $status,
        ]);

        $repository = new EloquentMediaRepository;
        $claimed = $repository->claimAcceptedForProcessing($workspaceId, (int) $asset->getKey());

        self::assertNull(
            $claimed,
            "MEDIA-PROCESS-CLAIM-NONACCEPTED-NOOP-01: {$status} durumundaki asset için claim no-op olmalı ve null dönmeli."
        );

        $asset->refresh();

        self::assertSame(
            $status,
            $asset->status,
            "MEDIA-PROCESS-CLAIM-NONACCEPTED-NOOP-01: {$status} durumundaki asset processing'e terfi ettirilmemeli."
        );

        self::assertNotSame('processing', $asset->status);
        self::assertSame($diskPath, $asset->disk_path);
    }

    public function test_cross_workspace_claim_and_second_claim_are_both_no_ops(): void
    {
        Storage::fake('local');
        $workspaceId = $this->persistedWorkspaceId('zeytin-media-process-claim-owner');
        $otherWorkspaceId = $this->persistedWorkspaceId('zeytin-media-process-claim-attacker');

        $diskPath = "quarantine/{$workspaceId}/cross-workspace-and-second-claim-asset";
        $bytes = 'cross-workspace-bytes';
        Storage::disk('local')->put($diskPath, $bytes);

        $asset = MediaAsset::query()->create([
            'workspace_id' => $workspaceId,
            'disk_path' => $diskPath,
            'original_name' => 'logo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => strlen($bytes),
            'alt_text' => 'Zeytin Restoranı logosu',
            'slot' => 'menu',
            'status' => MediaAssetStatus::Accepted->value,
        ]);

        $repository = new EloquentMediaRepository;

        $crossWorkspaceClaim = $repository->claimAcceptedForProcessing($otherWorkspaceId, (int) $asset->getKey());

        self::assertNull(
            $crossWorkspaceClaim,
            'MEDIA-PROCESS-CLAIM-CROSS-WORKSPACE-AND-SECOND-CLAIM-NOOP-01: farklı workspace\'in claim\'i null dönmeli.'
        );

        $asset->refresh();

        self::assertSame(
            MediaAssetStatus::Accepted->value,
            $asset->status,
            'MEDIA-PROCESS-CLAIM-CROSS-WORKSPACE-AND-SECOND-CLAIM-NOOP-01: cross-workspace claim asset\'i processing\'e terfi ettirmemeli.'
        );

        $firstClaim = $repository->claimAcceptedForProcessing($workspaceId, (int) $asset->getKey());

        self::assertInstanceOf(
            ProcessableMediaAsset::class,
            $firstClaim,
            'MEDIA-PROCESS-CLAIM-CROSS-WORKSPACE-AND-SECOND-CLAIM-NOOP-01: doğru workspace\'in ilk claim\'i başarılı olmalı.'
        );

        $secondClaim = $repository->claimAcceptedForProcessing($workspaceId, (int) $asset->getKey());

        self::assertNull(
            $secondClaim,
            'MEDIA-PROCESS-CLAIM-CROSS-WORKSPACE-AND-SECOND-CLAIM-NOOP-01: aynı asset ikinci kez claim edilememeli.'
        );

        $asset->refresh();

        self::assertSame(
            MediaAssetStatus::Processing->value,
            $asset->status,
            'MEDIA-PROCESS-CLAIM-CROSS-WORKSPACE-AND-SECOND-CLAIM-NOOP-01: ilk claim sonrası asset processing\'de kalmalı.'
        );

        self::assertSame($diskPath, $asset->disk_path);
        self::assertSame($bytes, Storage::disk('local')->get($diskPath));
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
