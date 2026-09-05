<?php

declare(strict_types=1);

namespace App\Application\Tenancy\Profile\Port;

use App\Application\Tenancy\Profile\Dto\LocationProfile;
use App\Domain\Tenancy\ValueObject\WeeklyOpeningHours;

interface LocationRepositoryPort
{
    public function findByWorkspaceAndId(int $workspaceId, int $locationId): ?LocationProfile;

    /**
     * @return list<LocationProfile>
     */
    public function listByWorkspaceAndBrand(int $workspaceId, int $brandId): array;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(int $workspaceId, int $brandId, array $attributes): LocationProfile;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  WeeklyOpeningHours|null  $openingHours  `null` "DOKUNMA" demektir;
     *                                                 `WeeklyOpeningHours::none()` ise "SİL". İkisi ayrı olmak
     *                                                 zorunda: alanı hiç göndermeyen eski bir istemci, adres
     *                                                 düzeltirken şubenin çalışma saatlerini sessizce
     *                                                 silmemelidir.
     */
    public function update(
        int $workspaceId,
        int $locationId,
        array $attributes,
        ?WeeklyOpeningHours $openingHours = null,
    ): ?LocationProfile;
}
