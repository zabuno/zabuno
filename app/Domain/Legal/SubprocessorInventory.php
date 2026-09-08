<?php

declare(strict_types=1);

namespace App\Domain\Legal;

/**
 * Alt işleyen envanteri — BUGÜN devrede olanlar, ve ölçülemeyenin itirafı.
 *
 * FF-228 (`docs/107` Faz 3.2).
 *
 * ═══ NEDEN "ÖLÇÜLEMEDİ" AYRI BİR DURUM ═══
 *
 * Kasa veritabanındadır. Veritabanı bir an okunamadığında boş bir liste
 * dönmek, "hiçbir alt işleyen yok" demekle aynı şeydir — ve bu, bir veri
 * işleme sözleşmesinde söylenebilecek en yanlış cümledir. Bilinmeyen bir
 * sonuç yeşil gösterilmez: envanter o durumda `vaultUnreadable` taşır ve
 * belge bunu okuyucuya SÖYLER. Aynı düşüş kararı gezintide de var
 * (`SiteNavigation::linkableRegistryPaths`), ama orada sessiz kalmak
 * zararsızdı; burada değil.
 */
final readonly class SubprocessorInventory
{
    /** @param  list<Subprocessor>  $active */
    public function __construct(
        public array $active,
        public bool $vaultUnreadable = false,
    ) {}
}
