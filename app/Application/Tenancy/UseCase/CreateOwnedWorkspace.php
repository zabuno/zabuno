<?php

declare(strict_types=1);

namespace App\Application\Tenancy\UseCase;

use App\Application\Tenancy\Dto\WorkspaceSummary;
use App\Application\Tenancy\Port\WorkspaceContextSessionPort;
use App\Application\Tenancy\Port\WorkspaceRepositoryPort;

final class CreateOwnedWorkspace
{
    public function __construct(
        private readonly WorkspaceRepositoryPort $workspaces,
        private readonly WorkspaceContextSessionPort $context,
    ) {}

    public function handle(string $name, int $ownerUserId): WorkspaceSummary
    {
        $summary = $this->workspaces->createOwnedWorkspace($name, $ownerUserId);

        $this->context->setCurrentWorkspaceId($summary->id);

        return $summary;
    }
}
