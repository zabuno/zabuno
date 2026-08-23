<?php

declare(strict_types=1);

namespace App\Application\Tenancy\UseCase;

use App\Application\Tenancy\Dto\WorkspaceSummary;
use App\Application\Tenancy\Port\WorkspaceRepositoryPort;

final class ListOwnWorkspaces
{
    public function __construct(
        private readonly WorkspaceRepositoryPort $workspaces,
    ) {}

    /**
     * @return list<WorkspaceSummary>
     */
    public function handle(int $userId): array
    {
        return $this->workspaces->listForUser($userId);
    }
}
