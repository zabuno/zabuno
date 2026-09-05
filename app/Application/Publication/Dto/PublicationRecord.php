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
        /**
         * Yayın anındaki plan hakları — DONDURULMUŞ (`docs/114` §3 Dalga 6).
         *
         * Sahip planını düşürdüğünde masadaki basılı karekod aynı kâğıttır
         * ve o kâğıdın gösterdiği yayın değişmemelidir; ödeme gecikmesi
         * masada oturan misafirin ekranını ortasından kesmez. Plan
         * değişikliği BİR SONRAKİ yayında etkisini gösterir.
         *
         * `null` = bu alan eklenmeden ÖNCE yapılmış bir yayın. Geriye dönük
         * bir liste uydurmak, o gün geçerli olmayan bir hakkı varmış gibi
         * göstermek olurdu; okuyan taraf `null` gördüğünde canlı plana düşer
         * ve bunu açıkça yapar.
         *
         * @var list<string>|null
         */
        public readonly ?array $entitlementKeys = null,
    ) {}
}
