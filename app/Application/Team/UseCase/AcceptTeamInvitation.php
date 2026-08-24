<?php

declare(strict_types=1);

namespace App\Application\Team\UseCase;

use App\Application\Team\Port\TeamInvitationRepositoryPort;
use App\Domain\Identity\ValueObject\EmailAddress;

final class AcceptTeamInvitation
{
    public function __construct(
        private readonly TeamInvitationRepositoryPort $invitations,
    ) {}

    public function handle(string $rawToken, string $rawEmail, int $userId): bool
    {
        $email = new EmailAddress($rawEmail);
        $tokenHash = hash('sha256', $rawToken);

        return $this->invitations->acceptPendingByTokenHash($tokenHash, $email->value(), $userId);
    }
}
