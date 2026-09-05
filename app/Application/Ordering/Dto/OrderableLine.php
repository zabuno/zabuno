<?php

declare(strict_types=1);

namespace App\Application\Ordering\Dto;

/**
 * Menünün SİPARİŞ EDİLEBİLİR satırı — sunucunun bildiği gerçek.
 *
 * Bu tip bilerek istemciden GELMEZ. Misafirin gönderdiği tek şey "hangi
 * satır, kaç adet"tir; ad, fiyat, para birimi ve alerjen her zaman burada,
 * sunucuda okunur. İstemcinin gönderdiği bir fiyata güvenmek, fiyatı
 * misafire yazdırmak olurdu.
 */
final class OrderableLine
{
    /** @param list<string> $allergens */
    public function __construct(
        public readonly int $menuItemId,
        public readonly string $productName,
        public readonly int $priceMinorAmount,
        public readonly string $currencyCode,
        public readonly array $allergens,
        /**
         * "Bugün bitti" işareti sipariş yolunda da geçerlidir
         * (`docs/115` M7). Menüde GÖRÜNÜR ama bugün ALINAMAZ: gizlemek
         * ile tükenmek ayrı eksenlerdir ve bu alan ikincisidir.
         */
        public readonly bool $isOutOfStock,
    ) {}
}
