<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Domain\Money\MoneyFormatter;

/**
 * Tek bir ürünün yapılandırılmış verisi — FF-116, `docs/105` §4.3.
 *
 * Kural `MenuStructuredData` ile aynıdır ve Google'ın kendi politikasının da
 * ilk maddesidir: **sayfada olmayan hiçbir şey işaretlenmez.** Veri, sayfayı
 * render eden anlık görüntünün ta kendisinden türetilir; ikinci bir kaynaktan
 * değil. Aksi hâlde iki kaynak zamanla ayrışır ve işaretleme, misafirin
 * gördüğünden farklı bir fiyat ilan eder.
 *
 * `BreadcrumbList` de burada kurulur çünkü ürün sayfasının menüye bağı
 * yapısaldır: arama sonucunda "Paşa Döner › Adana Kebap" görünmesi,
 * kullanıcının nereye gideceğini tıklamadan bilmesi demektir.
 */
final class MenuItemStructuredData
{
    /**
     * @param  array<string, mixed>  $item
     * @param  array{name: string, url: string}  $menu
     * @return array<string, mixed>
     */
    public static function forItem(array $item, string $canonicalUrl, string $categoryName, array $menu): array
    {
        $node = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'MenuItem',
            'name' => isset($item['productName']) ? (string) $item['productName'] : null,
            'url' => $canonicalUrl,
            // Açıklama SAYFADA YAZANIN aynısıdır. Alerjenleri açıklamaya
            // karıştırmıyoruz: ikisi ayrı bilgidir ve birleştirmek, restoranın
            // yazdığı cümleyi bizim eklediğimiz metinle bozardı.
            'description' => trim((string) ($item['description'] ?? '')) !== ''
                ? (string) $item['description']
                : null,
            'menuAddOn' => null,
        ], static fn (mixed $value): bool => $value !== null);

        $minor = $item['priceMinorAmount'] ?? null;
        $currency = isset($item['currencyCode']) ? (string) $item['currencyCode'] : null;

        if (is_numeric($minor) && $currency !== null && $currency !== '') {
            $digits = MoneyFormatter::fractionDigitsFor($currency);

            $node['offers'] = [
                '@type' => 'Offer',
                'price' => number_format((int) $minor / (10 ** $digits), $digits, '.', ''),
                'priceCurrency' => strtoupper($currency),
                'url' => $canonicalUrl,
            ];
        }

        $image = $item['image'] ?? null;

        if (is_array($image) && isset($image['url']) && (string) $image['url'] !== '') {
            $node['image'] = (string) $image['url'];
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                $node,
                self::breadcrumb($canonicalUrl, $categoryName, $menu, (string) ($item['productName'] ?? '')),
            ],
        ];
    }

    /**
     * @param  array{name: string, url: string}  $menu
     * @return array<string, mixed>
     */
    private static function breadcrumb(string $canonicalUrl, string $categoryName, array $menu, string $itemName): array
    {
        $items = [];
        $position = 1;

        if (trim($menu['name']) !== '') {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $menu['name'],
                'item' => $menu['url'],
            ];
        }

        // Kategori bir ADRESE sahip değildir; kırık bir bağlantı ilan etmek
        // yerine yalnız adı verilir.
        if (trim($categoryName) !== '') {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $categoryName,
            ];
        }

        $items[] = [
            '@type' => 'ListItem',
            'position' => $position,
            'name' => $itemName,
            'item' => $canonicalUrl,
        ];

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }
}
