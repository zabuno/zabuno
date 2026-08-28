<?php

declare(strict_types=1);

namespace App\Application\MenuCatalog\Csv;

/**
 * CSV satırlarını okunmuş, DOĞRULANMIŞ hâle çevirir — `docs/80`.
 *
 * Doğrulama ile YAZMA ayrıdır. Önce dosyanın tamamı okunur ve her satır
 * tek tek yargılanır; yazma ondan sonra, tek işlemde yapılır. Böylece iki
 * şey birden sağlanır: bozuk satırlar yüzünden geçerli 60 satır kaybolmaz,
 * ve yolun ortasında ölen bir aktarım yarım menü bırakmaz.
 */
final class MenuCsvImport
{
    /**
     * @param  list<array{category:string,product:string,priceMinorAmount:int,currencyCode:string,allergens:list<string>,description:?string,isVisible:bool}>  $rows
     * @param  list<array{line:int,reason:string}>  $rejected
     */
    private function __construct(
        public readonly array $rows,
        public readonly array $rejected,
    ) {}

    public static function parse(string $csv): self
    {
        $handle = fopen('php://temp', 'r+');
        // BOM, Excel'in Türkçe dosyalarda sıkça bıraktığı görünmez bir
        // başlangıçtır; temizlenmezse ilk sütun adı eşleşmez ve sahip
        // "dosyam neden geçersiz" diye sorar.
        fwrite($handle, preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv);
        rewind($handle);

        $rows = [];
        $rejected = [];
        $line = 0;
        $header = null;

        while (($cells = fgetcsv($handle)) !== false) {
            $line++;

            if ($cells === [null] || $cells === []) {
                continue;
            }

            if ($header === null) {
                $header = array_map(static fn ($cell): string => strtolower(trim((string) $cell)), $cells);

                continue;
            }

            if (count(array_filter($cells, static fn ($cell): bool => trim((string) $cell) !== '')) === 0) {
                continue;
            }

            $row = self::hydrate($header, $cells);
            $failure = self::reject($row);

            if ($failure !== null) {
                $rejected[] = ['line' => $line, 'reason' => $failure];

                continue;
            }

            $rows[] = self::normalize($row);
        }

        fclose($handle);

        if ($header === null) {
            $rejected[] = ['line' => 1, 'reason' => 'Dosyada başlık satırı bulunamadı.'];
        }

        return new self($rows, $rejected);
    }

    public function headerIsUsable(): bool
    {
        return $this->rows !== [] || $this->rejected === []
            || ($this->rejected[0]['reason'] ?? '') !== 'Dosyada başlık satırı bulunamadı.';
    }

    /**
     * @param  list<string>  $header
     * @param  list<string|null>  $cells
     * @return array<string,string>
     */
    private static function hydrate(array $header, array $cells): array
    {
        $row = [];

        foreach (MenuCsv::COLUMNS as $column) {
            $index = array_search($column, $header, true);
            $row[$column] = $index === false ? '' : MenuCsv::restore(trim((string) ($cells[$index] ?? '')));
        }

        return $row;
    }

    /** @param array<string,string> $row */
    private static function reject(array $row): ?string
    {
        if ($row['category'] === '') {
            return 'Kategori adı boş. Her satır bir kategoriye ait olmalı.';
        }

        if ($row['product'] === '') {
            return 'Ürün adı boş.';
        }

        if ($row['price'] === '') {
            return 'Fiyat boş.';
        }

        $price = str_replace(',', '.', $row['price']);

        if (! is_numeric($price)) {
            return "Fiyat sayı değil: \"{$row['price']}\".";
        }

        if ((float) $price <= 0) {
            return 'Fiyat sıfır ya da negatif olamaz.';
        }

        if ($row['currency'] === '' || strlen($row['currency']) !== 3) {
            return 'Para birimi üç harfli olmalı (örn. TRY).';
        }

        return null;
    }

    /**
     * @param  array<string,string>  $row
     * @return array{category:string,product:string,priceMinorAmount:int,currencyCode:string,allergens:list<string>,description:?string,isVisible:bool}
     */
    private static function normalize(array $row): array
    {
        $currency = strtoupper($row['currency']);
        $digits = MenuCsv::fractionDigits($currency);
        $price = (float) str_replace(',', '.', $row['price']);

        $allergens = array_values(array_filter(array_map(
            static fn (string $part): string => trim($part),
            explode(MenuCsv::ALLERGEN_SEPARATOR, $row['allergens']),
        ), static fn (string $part): bool => $part !== ''));

        return [
            'category' => $row['category'],
            'product' => $row['product'],
            // Kuruşa çevirirken YUVARLAMA şart: `(int) (52.50 * 100)` bazı
            // ondalıklarda 5249 verir ve menüdeki fiyat bir kuruş eksilir.
            'priceMinorAmount' => (int) round($price * (10 ** $digits)),
            'currencyCode' => $currency,
            'allergens' => $allergens,
            'description' => $row['description'] === '' ? null : $row['description'],
            // Belirtilmemişse GÖRÜNÜR: `docs/74`'teki sessiz duvar burada da
            // kurulmamalı.
            'isVisible' => ! in_array(strtolower($row['visible']), ['no', 'hayır', 'false', '0'], true),
        ];
    }
}
