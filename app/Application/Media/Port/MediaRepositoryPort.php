<?php

declare(strict_types=1);

namespace App\Application\Media\Port;

use App\Application\Media\Dto\MediaAssetSummary;
use App\Application\Media\Dto\MediaIntake;

interface MediaRepositoryPort
{
    public function intakeToQuarantine(int $workspaceId, MediaIntake $intake): MediaAssetSummary;

    /**
     * @return list<MediaAssetSummary>
     */
    public function listForWorkspace(int $workspaceId): array;

    public function find(int $id): ?MediaAssetSummary;

    public function delete(int $id): void;
}
