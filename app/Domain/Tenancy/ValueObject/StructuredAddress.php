<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\ValueObject;

use InvalidArgumentException;
use Symfony\Component\Intl\Countries;

final class StructuredAddress
{
    private function __construct(
        private readonly string $countryCode,
        private readonly string $city,
        private readonly string $addressLine1,
        private readonly ?string $addressLine2,
        private readonly ?string $postalCode,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['country_code', 'city', 'address_line1'] as $required) {
            if (! isset($data[$required]) || ! is_string($data[$required]) || $data[$required] === '') {
                throw new InvalidArgumentException("Structured address requires a non-empty '{$required}'.");
            }
        }

        if (! Countries::exists($data['country_code'])) {
            throw new InvalidArgumentException("Invalid ISO-3166-1 alpha-2 country code: {$data['country_code']}");
        }

        return new self(
            $data['country_code'],
            $data['city'],
            $data['address_line1'],
            isset($data['address_line2']) && $data['address_line2'] !== '' ? (string) $data['address_line2'] : null,
            isset($data['postal_code']) && $data['postal_code'] !== '' ? (string) $data['postal_code'] : null,
        );
    }

    public function countryCode(): string
    {
        return $this->countryCode;
    }

    public function city(): string
    {
        return $this->city;
    }

    public function addressLine1(): string
    {
        return $this->addressLine1;
    }

    public function addressLine2(): ?string
    {
        return $this->addressLine2;
    }

    public function postalCode(): ?string
    {
        return $this->postalCode;
    }
}
