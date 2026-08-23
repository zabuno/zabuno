<?php

declare(strict_types=1);

namespace App\Application\Team\UseCase;

use App\Application\Team\Dto\TeamInvitationSummary;
use App\Application\Team\Port\TeamInvitationRepositoryPort;

final class ListTeamInvitations
{
    public function __construct(
        private readonly TeamInvitationRepositoryPort $invitations,
    ) {}

    /**
     * @return list<TeamInvitationSummary>
     */
    public function handle(int $workspaceId): array
    {
        return $this->invitations->listPendingByWorkspaceId($workspaceId);
    }
}
