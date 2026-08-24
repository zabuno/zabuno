<?php

declare(strict_types=1);

namespace App\Application\Team\Dto;

use DateTimeInterface;

final class IssuedTeamInvitation
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $role,
        public readonly string $status,
        public readonly string $rawToken,
        public readonly DateTimeInterface $expiresAt,
        public readonly string $workspaceName,
    ) {}

    public function summary(): TeamInvitationSummary
    {
        return new TeamInvitationSummary($this->id, $this->email, $this->role, $this->status);
    }
}
