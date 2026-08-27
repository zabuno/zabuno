<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Domain\Money\MoneyFormatter;

/**
 * Yayınlanan menünün yapılandırılmış verisi (schema.org, JSON-LD).
 *
 * Tek kural, Google'ın kendi politikasının da ilk maddesidir: **sayfada
 * olmayan hiçbir şey işaretlenmez.** Bu yüzden veri, sayfayı render eden
 * anlık görüntünün TA KENDİSİNDEN türetilir; ikinci bir kaynaktan değil.
 * Aksi hâlde iki kaynak zamanla ayrışır ve işaretleme, kullanıcının
 * gördüğünden farklı bir fiyat ilan eder.
 *
 * Fiyat biçimlendirmesi burada YAPILMAZ: para birimi ve tutar ayrı alanlar
 * olarak verilir, çünkü schema.org sayısal değer bekler ve
 * yerelleştirilmiş bir metin (₺1.499,00) orada geçersizdir.
 */
final class MenuStructuredData
{
    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public static function forMenu(array $snapshot, string $canonicalUrl, ?string $restaurantName = null): array
    {
        $sections = [];

        foreach ((array) ($snapshot['categories'] ?? []) as $category) {
            if (! is_array($category)) {
                continue;
            }

            $items = [];

            foreach ((array) ($category['menuItems'] ?? []) as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $items[] = self::item($item);
            }

            $sections[] = array_filter([
                '@type' => 'MenuSection',
                'name' => isset($category['name']) ? (string) $category['name'] : null,
                'hasMenuItem' => $items === [] ? null : $items,
            ], static fn (mixed $value): bool => $value !== null);
        }

        $menu = array_filter([
            '@type' => 'Menu',
            'name' => 'Menü',
            'url' => $canonicalUrl,
            'hasMenuSection' => $sections === [] ? null : $sections,
        ], static fn (mixed $value): bool => $value !== null);

        // Restoran adı yoksa `Restaurant` düğümü kurulmaz. Adsız bir
        // restoran ilan etmek, doğrulayıcıda uyarı üretir ve hiçbir şey
        // kazandırmaz.
        if ($restaurantName === null || trim($restaurantName) === '') {
            return ['@context' => 'https://schema.org'] + $menu;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Restaurant',
            'name' => $restaurantName,
            'url' => $canonicalUrl,
            'hasMenu' => $menu,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private static function item(array $item): array
    {
        $node = array_filter([
            '@type' => 'MenuItem',
            'name' => isset($item['productName']) ? (string) $item['productName'] : null,
        ], static fn (mixed $value): bool => $value !== null);

        $minor = $item['priceMinorAmount'] ?? null;
        $currency = isset($item['currencyCode']) ? (string) $item['currencyCode'] : null;

        if (is_numeric($minor) && $currency !== null && $currency !== '') {
            $digits = MoneyFormatter::fractionDigitsFor($currency);

            $node['offers'] = [
                '@type' => 'Offer',
                // Sayısal değer: schema.org yerelleştirilmiş metin kabul
                // etmez. Bölen para biriminin kendi basamağından gelir.
                'price' => number_format((int) $minor / (10 ** $digits), $digits, '.', ''),
                'priceCurrency' => strtoupper($currency),
            ];
        }

        $allergens = $item['allergens'] ?? null;

        if (is_array($allergens) && $allergens !== []) {
            $node['suitableForDiet'] = null;
            unset($node['suitableForDiet']);
            // Alerjen listesi schema.org'da serbest metindir; uydurma bir
            // diyet sınıflandırmasına çevirmiyoruz.
            $node['description'] = 'Alerjenler: '.implode(', ', array_map('strval', $allergens));
        }

        return $node;
    }
}
