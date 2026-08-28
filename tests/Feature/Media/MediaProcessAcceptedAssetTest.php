<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\Dto\MediaProcessingOutcome;
use App\Application\Media\Dto\MediaProcessingResult;
use App\Application\Media\Port\MediaAssetProcessorPort;
use App\Application\Media\UseCase\ProcessAcceptedMediaAsset;
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
 * Blind targeted RED for the media processing-orchestration package (frozen
 * scope: MEDIA-PROCESS-02). ProcessAcceptedMediaAsset claims the exact
 * accepted asset into processing via the real EloquentMediaRepository,
 * invokes an injected inline fake/spy MediaAssetProcessorPort on the
 * claimed absolute path, then guards the terminal transition: explicit
 * Succeeded -> processing->ready, explicit Failed -> processing->failed,
 * Indeterminate safe-holds processing. Non-accepted states never reach the
 * processor because the repository's claim already no-ops before dispatch.
 * Cross-workspace and duplicate terminal invocation are no-ops at the same
 * atomic repository-guard boundary already proven in
 * MediaProcessingClaimTest, exercised here end-to-end through the use case.
 *
 * MediaAssetProcessorPort, MediaProcessingResult, MediaProcessingOutcome,
 * ProcessAcceptedMediaAsset and MediaAssetStatus::Ready/::Failed do not
 * exist yet, so this whole file is expected to fail RED (missing classes /
 * undefined enum cases) before any real DB/storage assertion runs.
 *
 * Requirement IDs: MEDIA-PROCESS-USECASE-SUCCEEDED-READY-01,
 * MEDIA-PROCESS-USECASE-FAILED-TERMINAL-01,
 * MEDIA-PROCESS-USECASE-INDETERMINATE-SAFEHOLD-01,
 * MEDIA-PROCESS-USECASE-NONACCEPTED-NO-PROCESSOR-CALL-01,
 * MEDIA-PROCESS-USECASE-CROSSWORKSPACE-AND-DUPLICATE-NOOP-01.
 */
final class MediaProcessAcceptedAssetTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_succeeded_result_makes_the_asset_ready_with_processor_receiving_the_absolute_path_and_key_and_bytes_unchanged(): void
    {
        Storage::fake('local');
        $workspaceId = $this->persistedWorkspaceId('zeytin-media-process-usecase-succeeded');

        $diskPath = "quarantine/{$workspaceId}/succeeded-asset";
        $bytes = 'succeeded-clean-bytes';
        Storage::disk('local')->put($diskPath, $bytes);
        $expectedAbsolutePath = Storage::disk('local')->path($diskPath);

        $asset = $this->persistMediaAsset($workspaceId, $diskPath, strlen($bytes), MediaAssetStatus::Accepted->value);

        $repository = new EloquentMediaRepository;
        $processor = new FakeMediaAssetProcessorPort(MediaProcessingOutcome::Succeeded);
        $useCase = new ProcessAcceptedMediaAsset($processor, $repository);

        $useCase($workspaceId, (int) $asset->getKey());

        self::assertSame(
            [$expectedAbsolutePath],
            $processor->receivedAbsolutePaths,
            'MEDIA-PROCESS-USECASE-SUCCEEDED-READY-01: processor, claim edilen asset\'in gerçek mutlak filesystem yolunu almalı.'
        );

        $asset->refresh();

        self::assertSame(
            MediaAssetStatus::Ready->value,
            $asset->status,
            'MEDIA-PROCESS-USECASE-SUCCEEDED-READY-01: explicit Succeeded, processing\'i ready\'ye taşımalı.'
        );

        self::assertSame(
            $diskPath,
            $asset->disk_path,
            'MEDIA-PROCESS-USECASE-SUCCEEDED-READY-01: use case, orijinal tenant-scoped disk_path anahtarını değiştirmemeli.'
        );

        self::assertSame(
            $bytes,
            Storage::disk('local')->get($diskPath),
            'MEDIA-PROCESS-USECASE-SUCCEEDED-READY-01: use case, gerçek dosya byte\'larını değiştirmemeli.'
        );
    }

    public function test_explicit_failed_result_makes_the_asset_terminally_failed_with_key_and_bytes_unchanged(): void
    {
        Storage::fake('local');
        $workspaceId = $this->persistedWorkspaceId('zeytin-media-process-usecase-failed');

        $diskPath = "quarantine/{$workspaceId}/failed-asset";
        $bytes = 'failing-clean-bytes';
        Storage::disk('local')->put($diskPath, $bytes);
        $expectedAbsolutePath = Storage::disk('local')->path($diskPath);

        $asset = $this->persistMediaAsset($workspaceId, $diskPath, strlen($bytes), MediaAssetStatus::Accepted->value);

        $repository = new EloquentMediaRepository;
        $processor = new FakeMediaAssetProcessorPort(MediaProcessingOutcome::Failed);
        $useCase = new ProcessAcceptedMediaAsset($processor, $repository);

        $useCase($workspaceId, (int) $asset->getKey());

        self::assertSame(
            [$expectedAbsolutePath],
            $processor->receivedAbsolutePaths,
            'MEDIA-PROCESS-USECASE-FAILED-TERMINAL-01: processor, claim edilen asset\'in gerçek mutlak filesystem yolunu almalı.'
        );

        $asset->refresh();

        self::assertSame(
            MediaAssetStatus::Failed->value,
            $asset->status,
            'MEDIA-PROCESS-USECASE-FAILED-TERMINAL-01: explicit Failed, processing\'i terminal failed\'e taşımalı.'
        );

        self::assertSame(
            $diskPath,
            $asset->disk_path,
            'MEDIA-PROCESS-USECASE-FAILED-TERMINAL-01: use case, orijinal disk_path anahtarını değiştirmemeli.'
        );

        self::assertSame(
            $bytes,
            Storage::disk('local')->get($diskPath),
            'MEDIA-PROCESS-USECASE-FAILED-TERMINAL-01: use case, gerçek dosya byte\'larını değiştirmemeli.'
        );
    }

    public function test_indeterminate_result_safe_holds_processing_without_any_terminal_transition(): void
    {
        Storage::fake('local');
        $workspaceId = $this->persistedWorkspaceId('zeytin-media-process-usecase-indeterminate');

        $diskPath = "quarantine/{$workspaceId}/indeterminate-asset";
        $bytes = 'indeterminate-clean-bytes';
        Storage::disk('local')->put($diskPath, $bytes);
        $expectedAbsolutePath = Storage::disk('local')->path($diskPath);

        $asset = $this->persistMediaAsset($workspaceId, $diskPath, strlen($bytes), MediaAssetStatus::Accepted->value);

        $repository = new EloquentMediaRepository;
        $processor = new FakeMediaAssetProcessorPort(MediaProcessingOutcome::Indeterminate);
        $useCase = new ProcessAcceptedMediaAsset($processor, $repository);

        $useCase($workspaceId, (int) $asset->getKey());

        self::assertSame(
            [$expectedAbsolutePath],
            $processor->receivedAbsolutePaths,
            'MEDIA-PROCESS-USECASE-INDETERMINATE-SAFEHOLD-01: processor yine de gerçek mutlak yol ile çağrılmalı.'
        );

        $asset->refresh();

        self::assertSame(
            MediaAssetStatus::Processing->value,
            $asset->status,
            'MEDIA-PROCESS-USECASE-INDETERMINATE-SAFEHOLD-01: Indeterminate hiçbir terminal geçişi tetiklememeli, asset processing\'de güvenli-beklemede kalmalı.'
        );

        self::assertSame($diskPath, $asset->disk_path);
        self::assertSame($bytes, Storage::disk('local')->get($diskPath));
    }

    /**
     * @return list<string>
     */
    public static function nonAcceptedNoOpStatusProvider(): array
    {
        return [
            'quarantined' => [MediaAssetStatus::Quarantined->value],
            'scanning' => [MediaAssetStatus::Scanning->value],
            'rejected' => [MediaAssetStatus::Rejected->value],
        ];
    }

    #[DataProvider('nonAcceptedNoOpStatusProvider')]
    public function test_non_accepted_states_never_invoke_the_processor(string $nonAcceptedStatus): void
    {
        Storage::fake('local');
        $workspaceId = $this->persistedWorkspaceId("zeytin-media-process-usecase-non-accepted-{$nonAcceptedStatus}");

        $diskPath = "quarantine/{$workspaceId}/non-accepted-asset-{$nonAcceptedStatus}";
        $bytes = 'untouched-bytes';
        Storage::disk('local')->put($diskPath, $bytes);

        $asset = $this->persistMediaAsset($workspaceId, $diskPath, strlen($bytes), $nonAcceptedStatus);

        $repository = new EloquentMediaRepository;
        $processor = new FakeMediaAssetProcessorPort(MediaProcessingOutcome::Succeeded);
        $useCase = new ProcessAcceptedMediaAsset($processor, $repository);

        $useCase($workspaceId, (int) $asset->getKey());

        self::assertSame(
            [],
            $processor->receivedAbsolutePaths,
            "MEDIA-PROCESS-USECASE-NONACCEPTED-NO-PROCESSOR-CALL-01: {$nonAcceptedStatus} durumunda claim no-op olduğundan processor asla çağrılmamalı."
        );

        $asset->refresh();

        self::assertSame(
            $nonAcceptedStatus,
            $asset->status,
            "MEDIA-PROCESS-USECASE-NONACCEPTED-NO-PROCESSOR-CALL-01: {$nonAcceptedStatus} durumundaki asset'in durumu değişmemeli."
        );

        self::assertSame($diskPath, $asset->disk_path);
        self::assertSame($bytes, Storage::disk('local')->get($diskPath));
    }

    public function test_cross_workspace_claim_is_a_no_op_then_correct_invocation_succeeds_once_and_duplicate_invocation_is_a_no_op(): void
    {
        Storage::fake('local');
        $ownerWorkspaceId = $this->persistedWorkspaceId('zeytin-media-process-usecase-owner');
        $otherWorkspaceId = $this->persistedWorkspaceId('zeytin-media-process-usecase-attacker');

        $diskPath = "quarantine/{$ownerWorkspaceId}/shared-asset";
        $bytes = 'shared-clean-bytes';
        Storage::disk('local')->put($diskPath, $bytes);
        $expectedAbsolutePath = Storage::disk('local')->path($diskPath);

        $asset = $this->persistMediaAsset($ownerWorkspaceId, $diskPath, strlen($bytes), MediaAssetStatus::Accepted->value);

        $repository = new EloquentMediaRepository;
        $processor = new FakeMediaAssetProcessorPort(MediaProcessingOutcome::Succeeded);
        $useCase = new ProcessAcceptedMediaAsset($processor, $repository);

        $useCase($otherWorkspaceId, (int) $asset->getKey());

        self::assertSame(
            [],
            $processor->receivedAbsolutePaths,
            'MEDIA-PROCESS-USECASE-CROSSWORKSPACE-AND-DUPLICATE-NOOP-01: farklı workspace çağrısı no-op olmalı, processor çağrılmamalı.'
        );

        $asset->refresh();

        self::assertSame(
            MediaAssetStatus::Accepted->value,
            $asset->status,
            'MEDIA-PROCESS-USECASE-CROSSWORKSPACE-AND-DUPLICATE-NOOP-01: cross-workspace çağrı asset\'i processing\'e terfi ettirmemeli.'
        );

        $useCase($ownerWorkspaceId, (int) $asset->getKey());

        self::assertSame(
            [$expectedAbsolutePath],
            $processor->receivedAbsolutePaths,
            'MEDIA-PROCESS-USECASE-CROSSWORKSPACE-AND-DUPLICATE-NOOP-01: doğru workspace çağrısı processor\'ü tam olarak bir kez çağırmalı.'
        );

        $asset->refresh();

        self::assertSame(
            MediaAssetStatus::Ready->value,
            $asset->status,
            'MEDIA-PROCESS-USECASE-CROSSWORKSPACE-AND-DUPLICATE-NOOP-01: doğru çağrı sonrası asset ready terminal durumuna ulaşmalı.'
        );

        $useCase($ownerWorkspaceId, (int) $asset->getKey());

        self::assertSame(
            [$expectedAbsolutePath],
            $processor->receivedAbsolutePaths,
            'MEDIA-PROCESS-USECASE-CROSSWORKSPACE-AND-DUPLICATE-NOOP-01: yinelenen (duplicate) terminal çağrı no-op olmalı, processor ikinci kez çağrılmamalı.'
        );

        $asset->refresh();

        self::assertSame(MediaAssetStatus::Ready->value, $asset->status);
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

    private function persistMediaAsset(int $workspaceId, string $diskPath, int $sizeBytes, string $status): MediaAsset
    {
        return MediaAsset::query()->create([
            'workspace_id' => $workspaceId,
            'disk_path' => $diskPath,
            'original_name' => 'logo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => $sizeBytes,
            'alt_text' => 'Zeytin Restoranı logosu',
            'slot' => 'menu',
            'status' => $status,
        ]);
    }
}

/**
 * Inline fake/spy processor port: records every absolute path it was
 * invoked with and always returns the injected fixed outcome.
 */
final class FakeMediaAssetProcessorPort implements MediaAssetProcessorPort
{
    /** @var list<string> */
    public array $receivedAbsolutePaths = [];

    public function __construct(private readonly MediaProcessingOutcome $outcome) {}

    public function process(string $absolutePath, string $slot = ''): MediaProcessingResult
    {
        $this->receivedAbsolutePaths[] = $absolutePath;

        return new MediaProcessingResult($this->outcome);
    }
}
