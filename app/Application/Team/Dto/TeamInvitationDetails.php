<?php

declare(strict_types=1);

namespace App\Application\Team\Dto;

final class TeamInvitationDetails
{
    private function __construct(
        public readonly string $state,
        public readonly bool $authenticated,
        public readonly ?string $workspaceName = null,
        public readonly ?string $email = null,
        public readonly ?string $role = null,
        public readonly ?string $acceptUrl = null,
    ) {}

    public static function guest(): self
    {
        return new self('guest', false);
    }

    public static function nonAvailable(string $state, bool $authenticated): self
    {
        return new self($state, $authenticated);
    }

    public static function available(string $workspaceName, string $email, string $role, string $acceptUrl): self
    {
        return new self('available', true, $workspaceName, $email, $role, $acceptUrl);
    }

    /**
     * @return array{state:string,authenticated:bool,sensitive:array<string,mixed>}
     */
    public function toArray(): array
    {
        $sensitive = $this->state === 'available'
            ? [
                'workspaceName' => $this->workspaceName,
                'email' => $this->email,
                'role' => $this->role,
                'acceptUrl' => $this->acceptUrl,
            ]
            : [];

        return [
            'state' => $this->state,
            'authenticated' => $this->authenticated,
            'sensitive' => $sensitive,
            ...$sensitive,
        ];
    }
}
