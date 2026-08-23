<?php

declare(strict_types=1);

namespace App\Application\Team\UseCase;

use App\Application\Team\Port\TeamMemberRepositoryPort;

final class RemoveTeamMember
{
    public function __construct(
        private readonly TeamMemberRepositoryPort $members,
    ) {}

    public function handle(int $workspaceId, int $membershipId): bool
    {
        return $this->members->removeEditor($workspaceId, $membershipId);
    }
}
