<?php

declare(strict_types=1);

namespace App\Application\MenuCatalog\Csv;

use App\Application\MenuCatalog\Dto\MenuDraftTree;

/**
 * Menünün taşınabilir hâli — `docs/80` (P0-05 CSV yolu, P0-09).
 *
 * Sahip "menümü alıp gidebilir miyim?" diye sorduğunda cevabın evet olması,
 * pilot restoranın kilitlenme korkusunu kaldıran şey. Aynı dosya geri de
 * yüklenebildiği için, bu bir çıkış kapısı değil bir KAPI.
 */
final class MenuCsv
{
    /** @var list<string> */
    public const COLUMNS = ['category', 'product', 'price', 'currency', 'allergens', 'description', 'visible'];

    /**
     * Alerjenler NOKTALI VİRGÜLLE ayrılır: virgül sütun ayracıdır ve aynı
     * hücrede ikinci bir anlam taşıyamaz.
     */
    public const ALLERGEN_SEPARATOR = ';';

    public static function fromDraftTree(MenuDraftTree $tree): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, self::COLUMNS);

        foreach ($tree->categories as $category) {
            foreach ($category['items'] as $item) {
                fputcsv($handle, [
                    self::neutralize((string) $category['name']),
                    self::neutralize((string) $item['productName']),
                    self::decimal((int) $item['priceMinorAmount'], (string) $item['currencyCode']),
                    (string) $item['currencyCode'],
                    self::neutralize(implode(self::ALLERGEN_SEPARATOR, $item['allergens'])),
                    self::neutralize((string) ($item['description'] ?? '')),
                    $item['isVisible'] ? 'yes' : 'no',
                ]);
            }
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Elektronik tablo, `=` `+` `-` `@` ile başlayan bir hücreyi FORMÜL
     * olarak çalıştırır. Menüsünü indiren sahibin makinesinde komut
     * çalıştırmak, bizim ürettiğimiz bir dosyayla olmamalı.
     *
     * Nötrleme tek tırnakla yapılır: hücre metin olarak okunur ve
     * kullanıcının yazdığı ad KAYBOLMAZ.
     */
    public static function neutralize(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return str_contains("=+-@\t\r", $value[0]) ? "'".$value : $value;
    }

    /** Nötrlemenin geri alınması: geri yüklenen menüde ad tırnakla başlamaz. */
    public static function restore(string $value): string
    {
        return str_starts_with($value, "'") ? substr($value, 1) : $value;
    }

    public static function decimal(int $minorAmount, string $currencyCode): string
    {
        $digits = self::fractionDigits($currencyCode);

        return number_format($minorAmount / (10 ** $digits), $digits, '.', '');
    }

    /**
     * Kuruşsuz para birimleri vardır (JPY, KRW). Her yerde iki hane
     * varsaymak, 380 yeni "3,80" yapardı.
     */
    public static function fractionDigits(string $currencyCode): int
    {
        return match (strtoupper($currencyCode)) {
            'JPY', 'KRW', 'VND', 'CLP', 'ISK' => 0,
            'BHD', 'KWD', 'OMR', 'TND', 'JOD' => 3,
            default => 2,
        };
    }
}
