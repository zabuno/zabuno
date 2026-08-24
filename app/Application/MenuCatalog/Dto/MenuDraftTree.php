<?php

declare(strict_types=1);

namespace App\Application\MenuCatalog\Dto;

final class MenuDraftTree
{
    /**
     * @param  list<array{id:int,name:string,position:int,items:list<array{id:int,productId:int,productName:string,priceMinorAmount:int,currencyCode:string,position:int,isVisible:bool,allergens:list<string>}>}>  $categories
     */
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly int $locationId,
        public readonly string $name,
        public readonly string $state,
        public readonly array $categories,
    ) {}
}
