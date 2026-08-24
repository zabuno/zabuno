<?php

declare(strict_types=1);

namespace App\Domain\Identity\ValueObject;

use App\Domain\Identity\Exception\InvalidEmailAddressException;

final class EmailAddress
{
    private string $normalized;

    public function __construct(string $rawValue)
    {
        $trimmed = trim($rawValue);
        $lower = strtolower($trimmed);

        if ($lower === '' || filter_var($lower, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidEmailAddressException("Invalid email address: {$rawValue}");
        }

        $this->normalized = $lower;
    }

    public function value(): string
    {
        return $this->normalized;
    }

    public function equals(self $other): bool
    {
        return $this->normalized === $other->normalized;
    }

    public function __toString(): string
    {
        return $this->normalized;
    }
}
