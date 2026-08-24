<?php

declare(strict_types=1);

namespace App\Application\Team\Dto;

final class TeamMemberSummary
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $role,
    ) {}

    /**
     * @return array{id:int,name:string,email:string,role:string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
        ];
    }
}
