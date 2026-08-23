<?php

declare(strict_types=1);

namespace App\Domain\Identity\ValueObject;

final class HashedPassword
{
    public function __construct(private readonly string $hash) {}

    public function value(): string
    {
        return $this->hash;
    }
}
