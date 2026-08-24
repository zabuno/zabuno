<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\ValueObject;

use InvalidArgumentException;

final class BrandSlug
{
    private function __construct(private readonly string $value) {}

    public static function fromString(string $value): self
    {
        if ($value === '' || preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $value) !== 1) {
            throw new InvalidArgumentException('Brand slug must be a non-empty lowercase kebab-case value.');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
