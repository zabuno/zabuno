<?php

declare(strict_types=1);

namespace App\Application\Media\Port;

use App\Application\Media\Dto\MediaAssetSummary;
use App\Application\Media\Dto\MediaIntake;
use App\Application\Media\Dto\ScannableMediaAsset;

interface MediaRepositoryPort
{
    public function intakeToQuarantine(int $workspaceId, MediaIntake $intake): MediaAssetSummary;

    /**
     * @return list<MediaAssetSummary>
     */
    public function listForWorkspace(int $workspaceId): array;

    public function find(int $id): ?MediaAssetSummary;

    public function delete(int $id): void;

    /**
     * Atomically claims the workspace's exact quarantined asset into
     * scanning. A non-quarantined or cross-workspace asset is a no-op.
     */
    public function claimQuarantinedForScanning(int $workspaceId, int $assetId): ?ScannableMediaAsset;

    public function markRejectedIfScanning(int $workspaceId, int $assetId): void;
}
