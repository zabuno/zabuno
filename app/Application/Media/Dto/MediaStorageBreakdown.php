<?php

declare(strict_types=1);

namespace App\Application\Media\Dto;

use App\Domain\Media\StorageCategory;

/**
 * "Yeri ne dolduruyor?" — bir çalışma alanının deposunun kırılımı
 * (`docs/108` §6.4).
 *
 * İki ayrı şey taşır ve ikisi bilerek ayrıdır:
 *
 *   - `categories`: kullanılabilir dosyaların AMACA göre dilimleri.
 *   - `trash`: çöpteki dosyalar. Kota çöpü İÇERİR (`config/media-quota.php`:
 *     silmek yer açmaz, kalıcı silme açar), bu yüzden çöp kırılımda kendi
 *     satırındadır ve kategorilerle TOPLANMAZ. Karıştırılırsa sahibin
 *     elindeki tek geri kazanma düğmesi görünmez olur.
 */
final class MediaStorageBreakdown
{
    /**
     * @param  array<int, array{key: string, bytes: int, assets: int}>  $categories
     */
    public function __construct(
        public readonly array $categories,
        public readonly int $trashBytes,
        public readonly int $trashAssets,
    ) {}

    /**
     * Ham sayımdan kırılım kurar: kategori toplanır, boş kategori DÜŞER.
     *
     * Sıfır baytlık dört satır, "bende hiçbir şey yok" cümlesini dört kez
     * söyler ve gerçek dilimi görsel gürültüye gömer.
     *
     * @param  iterable<int, array{slot: string, bytes: int, assets: int}>  $usableRows
     */
    public static function fromRows(iterable $usableRows, int $trashBytes, int $trashAssets): self
    {
        /** @var array<string, array{key: string, bytes: int, assets: int}> $totals */
        $totals = [];

        foreach ($usableRows as $row) {
            $key = StorageCategory::forSlot($row['slot'])->value;
            $totals[$key] ??= ['key' => $key, 'bytes' => 0, 'assets' => 0];
            $totals[$key]['bytes'] += $row['bytes'];
            $totals[$key]['assets'] += $row['assets'];
        }

        $categories = array_values(array_filter(
            $totals,
            static fn (array $row): bool => $row['bytes'] > 0 || $row['assets'] > 0,
        ));

        /*
            EN BÜYÜK DİLİM BAŞTA. Sahip listeyi taramaz, ilk satırı okur ve
            kararını verir. Eşitlikte kategori anahtarı sırayı belirler ki
            aynı veri iki istekte iki farklı sıra üretmesin.
        */
        usort(
            $categories,
            static fn (array $a, array $b): int => $b['bytes'] <=> $a['bytes'] ?: strcmp($a['key'], $b['key']),
        );

        return new self($categories, $trashBytes, $trashAssets);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'categories' => $this->categories,
            'trash' => [
                'bytes' => $this->trashBytes,
                'assets' => $this->trashAssets,
            ],
        ];
    }
}
