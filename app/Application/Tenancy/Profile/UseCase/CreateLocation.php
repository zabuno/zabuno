<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Profile\UseCase;

use App\Application\Tenancy\Profile\Dto\LocationProfile;
use App\Application\Tenancy\Profile\Port\BrandRepositoryPort;
use App\Application\Tenancy\Profile\Port\LocationRepositoryPort;
use App\Domain\Tenancy\ValueObject\StructuredAddress;

final class CreateLocation
{
    public function __construct(
        private readonly BrandRepositoryPort $brands,
        private readonly LocationRepositoryPort $locations,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(int $workspaceId, array $data): ?LocationProfile
    {
        $brand = $this->brands->findByWorkspaceId($workspaceId);

        if ($brand === null) {
            return null;
        }

        $address = StructuredAddress::fromArray($data);

        return $this->locations->create($workspaceId, $brand->id, [
            'display_name' => $data['display_name'],
            'country_code' => $address->countryCode(),
            'city' => $address->city(),
            'address_line1' => $address->addressLine1(),
            'address_line2' => $address->addressLine2(),
            'postal_code' => $address->postalCode(),
        ]);
    }
}
