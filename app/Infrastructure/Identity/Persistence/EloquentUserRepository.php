<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity\Persistence;

use App\Application\Identity\Port\UserRepositoryPort;
use App\Domain\Identity\Exception\EmailAlreadyRegisteredException;
use App\Domain\Identity\ValueObject\EmailAddress;
use App\Domain\Identity\ValueObject\HashedPassword;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

final class EloquentUserRepository implements UserRepositoryPort
{
    public function existsWithEmail(EmailAddress $email): bool
    {
        return User::query()->where('email', $email->value())->exists();
    }

    public function createPendingUser(EmailAddress $email, string $name, HashedPassword $password): int
    {
        try {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email->value(),
                'password' => $password->value(),
                'email_verified_at' => null,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new EmailAlreadyRegisteredException('Email already registered.');
        }

        return (int) $user->getKey();
    }
}
