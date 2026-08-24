<?php

declare(strict_types=1);

namespace App\Application\Team\UseCase;

use App\Application\Team\Port\TeamInvitationRepositoryPort;

final class CancelTeamInvitation
{
    public function __construct(
        private readonly TeamInvitationRepositoryPort $invitations,
    ) {}

    public function handle(int $workspaceId, int $invitationId): bool
    {
        return $this->invitations->cancelPending($workspaceId, $invitationId);
    }
}
