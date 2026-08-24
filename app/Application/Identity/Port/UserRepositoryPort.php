<?php

declare(strict_types=1);

namespace App\Application\Identity\Port;

use App\Domain\Identity\ValueObject\EmailAddress;
use App\Domain\Identity\ValueObject\HashedPassword;

interface UserRepositoryPort
{
    public function existsWithEmail(EmailAddress $email): bool;

    public function createPendingUser(EmailAddress $email, string $name, HashedPassword $password): int;
}
