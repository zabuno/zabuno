<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Profile\Dto;

final class LocationProfile
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly int $brandId,
        public readonly string $displayName,
        public readonly string $countryCode,
        public readonly string $city,
        public readonly string $addressLine1,
        public readonly ?string $addressLine2,
        public readonly ?string $postalCode,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspaceId,
            'brand_id' => $this->brandId,
            'display_name' => $this->displayName,
            'country_code' => $this->countryCode,
            'city' => $this->city,
            'address_line1' => $this->addressLine1,
            'address_line2' => $this->addressLine2,
            'postal_code' => $this->postalCode,
        ];
    }
}
