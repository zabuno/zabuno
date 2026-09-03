<?php

declare(strict_types=1);

namespace App\Application\Ai\Batch;

/**
 * TOPLAYICI (collector) — `docs/98` FF-75, `agents/collector.md`.
 *
 * Sayfa sayfa okunan artifact'ları TEK inceleme listesine toplar ve
 * yinelenenleri ayıklar: 40 sayfalık bir menüde "Ayran" iki sayfada da
 * çıkabilir; sahibin iki kez "ekle" demesi gerekmez. Yinelenen SİLİNMEZ,
 * sayılır — sahip kaç satırın atlandığını okur.
 *
 * Toplayıcı YAZMAZ: sonuç, mevcut insan-onaylı `apply` akışına gider.
 */
final class MenuBatchCollector
{
    /**
     * @param  list<array{artifactId:int, page:int, fields:list<array{name:string,value:array<string,mixed>,confidence:float,uncertain:bool}>}>  $artifacts
     * @param  list<array{mediaAssetId:int, reason:string}>  $failedPages
     * @return array{rows:list<array<string,mixed>>, artifactIds:list<int>, duplicatesSkipped:int, failedPages:list<array{mediaAssetId:int, reason:string}>}
     */
    public function collect(array $artifacts, array $failedPages): array
    {
        $rows = [];
        $seen = [];
        $duplicates = 0;
        $artifactIds = [];

        foreach ($artifacts as $artifact) {
            $artifactIds[] = $artifact['artifactId'];

            foreach ($artifact['fields'] as $field) {
                $value = $field['value'];
                $key = self::normalise((string) ($value['category'] ?? '')).'|'.self::normalise((string) ($value['product'] ?? ''));

                if (isset($seen[$key])) {
                    $duplicates++;

                    continue;
                }

                $seen[$key] = true;
                $rows[] = [
                    'artifactId' => $artifact['artifactId'],
                    'page' => $artifact['page'],
                    'name' => $field['name'],
                    'category' => (string) ($value['category'] ?? ''),
                    'product' => (string) ($value['product'] ?? ''),
                    'priceMinorAmount' => $value['priceMinorAmount'] ?? null,
                    'currencyCode' => (string) ($value['currencyCode'] ?? ''),
                    'confidence' => $field['confidence'],
                    'uncertain' => $field['uncertain'],
                ];
            }
        }

        return [
            'rows' => $rows,
            'artifactIds' => array_values(array_unique($artifactIds)),
            'duplicatesSkipped' => $duplicates,
            'failedPages' => array_values($failedPages),
        ];
    }

    private static function normalise(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? ''));
    }
}
