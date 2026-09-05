<?php

declare(strict_types=1);

namespace App\Application\Ordering\Dto;

/**
 * Ekranda GÖRÜNEN sipariş satırı — siparişin yazıldığı andaki hâliyle.
 *
 * `OrderLineDraft` yazma yolunun tipidir; bu ise okuma yolunun. İkisi
 * bugün neredeyse aynı alanları taşıyor ve yine de ayrı duruyorlar: yazma
 * tipi bir gün menü kimliği ya da vergi alanı kazanabilir, okuma tipi ise
 * mutfağın gördüğü fişin sözleşmesidir. Tek tip olsalardı, yazma yoluna
 * eklenen her alan sessizce mutfak monitörüne de düşerdi.
 */
final class OrderLineSummary
{
    /** @param list<string> $allergens */
    public function __construct(
        public readonly string $productName,
        public readonly int $quantity,
        public readonly int $unitPriceMinorAmount,
        public readonly int $lineTotalMinorAmount,
        public readonly string $currencyCode,
        public readonly array $allergens,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'productName' => $this->productName,
            'quantity' => $this->quantity,
            'unitPriceMinorAmount' => $this->unitPriceMinorAmount,
            'lineTotalMinorAmount' => $this->lineTotalMinorAmount,
            'currencyCode' => $this->currencyCode,
            // K4: alerjen SATIRIN kendisindedir. Ekranın ürüne gidip bakması
            // gerekseydi, o ürün silindiğinde uyarı da silinirdi.
            'allergens' => $this->allergens,
        ];
    }
}
