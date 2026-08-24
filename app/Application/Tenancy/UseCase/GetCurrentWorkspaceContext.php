<?php

declare(strict_types=1);

namespace App\Application\Tenancy\UseCase;

use App\Application\Tenancy\Dto\WorkspaceSummary;
use App\Application\Tenancy\Port\WorkspaceContextSessionPort;
use App\Application\Tenancy\Port\WorkspaceRepositoryPort;

final class GetCurrentWorkspaceContext
{
    public function __construct(
        private readonly WorkspaceRepositoryPort $workspaces,
        private readonly WorkspaceContextSessionPort $context,
    ) {}

    /**
     * Revalidates the session-held workspace id against membership + state
     * on every call. A missing, stale, foreign, or ineligible-state context
     * is silently cleared and reported as missing (null) rather than leaked.
     */
    public function handle(int $userId): ?WorkspaceSummary
    {
        $workspaceId = $this->context->getCurrentWorkspaceId();

        if ($workspaceId === null) {
            return null;
        }

        $summary = $this->workspaces->findEligibleForUser($workspaceId, $userId);

        if ($summary === null) {
            $this->context->clearCurrentWorkspaceId();

            return null;
        }

        return $summary;
    }
}
