<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Profile\UseCase;

use App\Application\Tenancy\Profile\Dto\LocationProfile;
use App\Application\Tenancy\Profile\Port\LocationRepositoryPort;
use App\Domain\Tenancy\ValueObject\StructuredAddress;

final class UpdateLocation
{
    public function __construct(
        private readonly LocationRepositoryPort $locations,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(int $workspaceId, int $locationId, array $data): ?LocationProfile
    {
        $address = StructuredAddress::fromArray($data);

        return $this->locations->update($workspaceId, $locationId, [
            'display_name' => $data['display_name'],
            'country_code' => $address->countryCode(),
            'city' => $address->city(),
            'address_line1' => $address->addressLine1(),
            'address_line2' => $address->addressLine2(),
            'postal_code' => $address->postalCode(),
        ]);
    }
}
