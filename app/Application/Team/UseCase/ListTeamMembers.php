<?php

declare(strict_types=1);

namespace App\Application\Team\UseCase;

use App\Application\Team\Dto\TeamMemberSummary;
use App\Application\Team\Port\TeamMemberRepositoryPort;

final class ListTeamMembers
{
    public function __construct(
        private readonly TeamMemberRepositoryPort $members,
    ) {}

    /**
     * @return list<TeamMemberSummary>
     */
    public function handle(int $workspaceId): array
    {
        return $this->members->listByWorkspaceId($workspaceId);
    }
}
