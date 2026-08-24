<?php

declare(strict_types=1);

namespace App\Application\Identity\Port;

use App\Domain\Identity\ValueObject\HashedPassword;

interface PasswordHasherPort
{
    public function hash(string $plainPassword): HashedPassword;
}
