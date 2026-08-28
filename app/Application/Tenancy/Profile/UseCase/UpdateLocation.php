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

        $attributes = [
            'display_name' => $data['display_name'],
            'country_code' => $address->countryCode(),
            'city' => $address->city(),
            'address_line1' => $address->addressLine1(),
            'address_line2' => $address->addressLine2(),
            'postal_code' => $address->postalCode(),
        ];

        /*
            Saat dilimi YALNIZ gönderildiğinde yazılır. Her istekte yazsaydı,
            alanı taşımayan eski bir istemci şubenin saat dilimini sessizce
            siler ve yayın saatleri o anda kayardı.
        */
        if (is_string($data['timezone'] ?? null) && $data['timezone'] !== '') {
            $attributes['timezone'] = $data['timezone'];
        }

        return $this->locations->update($workspaceId, $locationId, $attributes);
    }
}
