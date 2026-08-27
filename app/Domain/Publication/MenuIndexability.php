<?php

declare(strict_types=1);

namespace App\Domain\Publication;

/**
 * Bir yayınlanmış menü arama motoruna açılmalı mı? — `URL-SEO-v1` Faz 1.4.
 *
 * Owner kararı "menüler arama motorunda görünsün" idi; bu, "HER menü
 * görünsün" demek değildir. Boş, denemelik veya tek satırlık bir menüyü
 * indekse sokmak iki tarafa da zarar verir: alan adının genel kalitesi
 * düşer ve restoran, müşterisine boş bir sayfa gösteren bir sonuçla
 * temsil edilir.
 *
 * Kural saf ve tek yerdedir; sitemap ile sayfanın kendi robots sinyali
 * AYNI cevabı vermek zorundadır, aksi hâlde arama motoruna çelişki
 * gönderilir.
 */
final class MenuIndexability
{
    /**
     * Bir menünün indekslenmesi için gereken en az içerik.
     *
     * Eşik kasten düşüktür: kapı kalite yargısı vermez, YOKLUĞU eler.
     * "İyi menü" tanımlamak bizim işimiz değil; boş sayfayı arama
     * sonucuna sokmamak bizim işimiz.
     */
    public const MINIMUM_VISIBLE_ITEMS = 1;

    /**
     * @param  array<string, mixed>  $snapshot  Yayınlanmış anlık görüntü
     */
    public static function isIndexable(array $snapshot): bool
    {
        return self::visibleItemCount($snapshot) >= self::MINIMUM_VISIBLE_ITEMS;
    }

    /** @param array<string, mixed> $snapshot */
    public static function visibleItemCount(array $snapshot): int
    {
        $categories = $snapshot['categories'] ?? [];

        if (! is_array($categories)) {
            return 0;
        }

        $count = 0;

        foreach ($categories as $category) {
            if (! is_array($category)) {
                continue;
            }

            $items = $category['menuItems'] ?? [];

            if (is_array($items)) {
                // Anlık görüntü zaten yalnız görünür ürünleri taşır:
                // gizlenen bir ürün yayına hiç girmez (CRIT-JOURNEY-
                // VISIBILITY-01). Burada sayılan şey, misafirin GERÇEKTEN
                // göreceği satır sayısıdır.
                $count += count($items);
            }
        }

        return $count;
    }
}
