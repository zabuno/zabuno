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
}
