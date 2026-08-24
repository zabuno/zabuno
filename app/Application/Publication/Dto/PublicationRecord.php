<?php

declare(strict_types=1);

namespace App\Application\Publication\Dto;

final class PublicationRecord
{
    /**
     * @param  array{categories: list<array{name:string,menuItems:list<array{productName:string,priceMinorAmount:int,currencyCode:string,allergens:list<string>}>}>}  $snapshot
     */
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly int $menuId,
        public readonly int $locationId,
        public readonly int $version,
        public readonly string $state,
        public readonly string $publishedAt,
        public readonly array $snapshot,
    ) {}
}
