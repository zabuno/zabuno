<?php

declare(strict_types=1);

namespace App\Application\Team\Dto;

final class TeamInvitationSummary
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $role,
        public readonly string $status,
    ) {}

    /**
     * @return array{id:int,email:string,role:string,status:string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
        ];
    }
}
