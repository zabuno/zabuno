<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\ValueObject;

use InvalidArgumentException;
use Symfony\Component\Intl\Currencies;

final class CurrencyCode
{
    private function __construct(private readonly string $value) {}

    public static function fromString(string $value): self
    {
        if (preg_match('/^[A-Z]{3}$/', $value) !== 1 || ! Currencies::exists($value)) {
            throw new InvalidArgumentException("Invalid ISO-4217 currency code: {$value}");
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
