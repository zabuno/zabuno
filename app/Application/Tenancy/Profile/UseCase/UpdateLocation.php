<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Profile\UseCase;

use App\Application\Tenancy\Profile\Dto\LocationProfile;
use App\Application\Tenancy\Profile\Port\LocationRepositoryPort;
use App\Domain\Tenancy\ValueObject\StructuredAddress;
use App\Domain\Tenancy\ValueObject\WeeklyOpeningHours;

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

        /*
            ÇALIŞMA SAATLERİ — üç ayrı hâl, üç ayrı davranış (`docs/109` §6.4).

            1. Alan HİÇ YOKSA → dokunulmaz (`null`). Saat diliminde olduğu
               gibi: alanı taşımayan eski bir istemci, hiç bilmediği bir
               veriyi silmemelidir. Adresini düzelten bir kullanıcı,
               şubesinin çalışma saatlerini kaybetmez.
            2. Alan BOŞ ya da `null` ise → silinir. "Artık söylemiyorum" da
               bir karardır ve kart o satırı bir daha çizmez.
            3. Yedi gün geldiyse → hafta yazılır.
        */
        $openingHours = null;

        if (array_key_exists('opening_hours', $data)) {
            $openingHours = WeeklyOpeningHours::fromArray(
                is_array($data['opening_hours']) ? $data['opening_hours'] : [],
            );
        }

        return $this->locations->update($workspaceId, $locationId, $attributes, $openingHours);
    }
}
