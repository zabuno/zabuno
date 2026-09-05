<?php

declare(strict_types=1);

namespace App\Application\MenuCatalog\Api\Dto;

/**
 * Bir menü satırının, HTTP katmanının karar vermek için ihtiyaç duyduğu
 * bağlamı.
 *
 * FF-154 ile bağlam, satırın O ANKİ hâlini de taşır. Sebep: denetim izinde
 * "öncesi" olmadan bir fiyat kaydı işe yaramaz ("bir şey değişti" sahibin
 * sorusunu kapatmaz, "380'den 420'ye çıktı" kapatır) ve öncesi yalnız
 * yazmadan ÖNCE okunabilir. Bu alanlar için ikinci bir sorgu açmak yerine
 * zaten yapılan tek sorguya birkaç sütun daha eklendi — denetim izinin
 * bedeli kontrolcü başına fazladan bir gidiş-dönüş olmamalı.
 *
 * Alanların HİÇBİRİ varsayılan taşımaz: "0 lira" ya da "" adlı bir ürün,
 * kaydı sessizce yanlış yazdıracak türden bir yer tutucudur.
 */
final class MenuItemApiContext
{
    public function __construct(
        public readonly int $workspaceId,
        public readonly int $productId,
        public readonly string $brandCurrencyCode,
        /** Satırın bağlı olduğu menü: kayıt "hangi menüde" sorusunu da kapatır. */
        public readonly int $menuId,
        /** Ürünün o anki adı — silinen bir satırın kaydındaki tek kimlik. */
        public readonly string $productName,
        public readonly int $priceMinorAmount,
        /** Satırın KENDİ para birimi; markanınkiyle aynı olmak zorunda değil. */
        public readonly string $currencyCode,
        public readonly bool $isVisible,
    ) {}
}
