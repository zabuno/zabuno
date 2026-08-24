<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Port;

use App\Application\Tenancy\Dto\WorkspaceSummary;

interface WorkspaceRepositoryPort
{
    public function createOwnedWorkspace(string $name, int $ownerUserId): WorkspaceSummary;

    /**
     * @return list<WorkspaceSummary>
     */
    public function listForUser(int $userId): array;

    /**
     * Returns the workspace only when the given user is a member of it AND
     * its state is eligible as a current context (onboarding|active).
     * Returns null uniformly for foreign, nonexistent, and ineligible-state
     * workspaces so callers stay enumeration-safe.
     */
    public function findEligibleForUser(int $workspaceId, int $userId): ?WorkspaceSummary;
}
