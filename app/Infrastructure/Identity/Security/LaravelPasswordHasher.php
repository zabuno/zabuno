<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity\Security;

use App\Application\Identity\Port\PasswordHasherPort;
use App\Domain\Identity\ValueObject\HashedPassword;
use Illuminate\Support\Facades\Hash;

final class LaravelPasswordHasher implements PasswordHasherPort
{
    public function hash(string $plainPassword): HashedPassword
    {
        return new HashedPassword(Hash::make($plainPassword));
    }
}
