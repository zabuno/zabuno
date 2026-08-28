<?php

declare(strict_types=1);

namespace App\Application\Publication\Port;

use App\Application\Publication\Dto\PublicationRecord;
use App\Application\Publication\Exception\PublicationPersistenceFailedException;

interface PublicationRepositoryPort
{
    /**
     * @param  array{categories: list<array{name:string,menuItems:list<array{productName:string,priceMinorAmount:int,currencyCode:string,allergens:list<string>}>}>}  $snapshot
     *
     * @throws PublicationPersistenceFailedException
     */
    public function publish(int $workspaceId, int $menuId, int $locationId, array $snapshot, int $publishedByUserId): PublicationRecord;

    public function current(int $workspaceId, int $menuId): ?PublicationRecord;

    /**
     * Menünün yayın geçmişi — EN YENİ ÖNCE.
     *
     * Sıra keyfi değil: geri almayı arayan sahip panik hâlindedir ve
     * listenin dibine inmez (`docs/81`).
     *
     * @return list<PublicationRecord>
     */
    public function history(int $workspaceId, int $menuId): array;

    public function find(int $workspaceId, int $menuId, int $publicationId): ?PublicationRecord;
}
