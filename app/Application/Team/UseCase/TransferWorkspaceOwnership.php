<?php

declare(strict_types=1);

namespace App\Application\Team\UseCase;

use App\Application\Team\Port\TeamMemberRepositoryPort;

final class TransferWorkspaceOwnership
{
    public function __construct(
        private readonly TeamMemberRepositoryPort $members,
    ) {}

    public function handle(int $workspaceId, int $requesterUserId, int $targetMembershipId): bool
    {
        return $this->members->transferOwnership($workspaceId, $requesterUserId, $targetMembershipId);
    }
}
