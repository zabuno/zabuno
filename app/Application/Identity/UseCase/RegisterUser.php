<?php

declare(strict_types=1);

namespace App\Application\Identity\UseCase;

use App\Application\Identity\Port\PasswordHasherPort;
use App\Application\Identity\Port\UserRepositoryPort;
use App\Domain\Identity\Exception\EmailAlreadyRegisteredException;
use App\Domain\Identity\ValueObject\EmailAddress;

final class RegisterUser
{
    public function __construct(
        private readonly UserRepositoryPort $users,
        private readonly PasswordHasherPort $hasher,
    ) {}

    public function handle(string $name, string $rawEmail, string $rawPassword): int
    {
        $email = new EmailAddress($rawEmail);

        if ($this->users->existsWithEmail($email)) {
            throw new EmailAlreadyRegisteredException('Email already registered.');
        }

        $hashedPassword = $this->hasher->hash($rawPassword);

        return $this->users->createPendingUser($email, $name, $hashedPassword);
    }
}
