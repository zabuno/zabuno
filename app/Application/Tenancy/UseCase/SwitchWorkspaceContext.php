<?php

declare(strict_types=1);

namespace App\Application\Tenancy\UseCase;

use App\Application\Tenancy\Dto\WorkspaceSummary;
use App\Application\Tenancy\Port\WorkspaceContextSessionPort;
use App\Application\Tenancy\Port\WorkspaceRepositoryPort;

final class SwitchWorkspaceContext
{
    public function __construct(
        private readonly WorkspaceRepositoryPort $workspaces,
        private readonly WorkspaceContextSessionPort $context,
    ) {}

    /**
     * Returns null uniformly for foreign, nonexistent, and ineligible-state
     * workspace ids so the Delivery layer can render one enumeration-safe
     * 404 signature.
     */
    public function handle(int $workspaceId, int $userId): ?WorkspaceSummary
    {
        $summary = $this->workspaces->findEligibleForUser($workspaceId, $userId);

        if ($summary === null) {
            return null;
        }

        $this->context->setCurrentWorkspaceId($summary->id);

        return $summary;
    }
}
