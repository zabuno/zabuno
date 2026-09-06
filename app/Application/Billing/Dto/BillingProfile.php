<?php

declare(strict_types=1);

namespace App\Application\Billing\Dto;

/**
 * Fatura profili — ödemeyi yapan tüzel/gerçek kişinin kimliği.
 *
 * Sağlayıcıya giden alıcı bilgisi BURADAN kopyalanır; hiçbir alan
 * uydurulmaz. Kart bilgisi bu nesnede yoktur ve olamaz.
 */
final readonly class BillingProfile
{
    public function __construct(
        public string $legalName,
        public string $taxNumber,
        public string $taxOffice,
        public string $address,
        public string $city,
        public string $country,
        public string $email,
        public string $phone,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            legalName: (string) $row['legal_name'],
            taxNumber: (string) $row['tax_number'],
            taxOffice: (string) $row['tax_office'],
            address: (string) $row['address'],
            city: (string) $row['city'],
            country: (string) $row['country'],
            email: (string) $row['email'],
            phone: (string) $row['phone'],
        );
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'legal_name' => $this->legalName,
            'tax_number' => $this->taxNumber,
            'tax_office' => $this->taxOffice,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'email' => $this->email,
            'phone' => $this->phone,
        ];
    }
}
