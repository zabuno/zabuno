<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\ValueObject;

use DateTimeZone;
use InvalidArgumentException;

final class TimezoneIdentifier
{
    private function __construct(private readonly string $value) {}

    public static function fromString(string $value): self
    {
        if (! in_array($value, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException("Invalid IANA timezone identifier: {$value}");
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
