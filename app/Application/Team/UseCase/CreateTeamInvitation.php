<?php

declare(strict_types=1);

namespace App\Application\Team\UseCase;

use App\Application\Team\Dto\IssuedTeamInvitation;
use App\Application\Team\Exception\TeamInvitationConflictException;
use App\Application\Team\Port\TeamInvitationRepositoryPort;
use App\Domain\Identity\ValueObject\EmailAddress;

final class CreateTeamInvitation
{
    public function __construct(
        private readonly TeamInvitationRepositoryPort $invitations,
    ) {}

    /**
     * @throws TeamInvitationConflictException
     */
    public function handle(int $workspaceId, string $rawEmail, string $role, int $invitedBy): IssuedTeamInvitation
    {
        $email = new EmailAddress($rawEmail);

        return $this->invitations->createPendingWithToken($workspaceId, $email->value(), $role, $invitedBy);
    }
}
