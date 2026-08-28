<?php

declare(strict_types=1);

namespace App\Application\Analytics\Dto;

final class AnalyticsSummary
{
    /**
     * @param  list<AnalyticsBreakdownRow>  $locations
     * @param  list<AnalyticsBreakdownRow>  $qrCodes
     */
    public function __construct(
        public readonly string $range,
        public readonly int $qrResolveCount,
        public readonly int $menuOpenCount,
        /**
         * YAKLAŞIK benzersiz ziyaretçi — `docs/68`.
         *
         * Aynı masadaki bir müşterinin menüyü altı kez açması altı müşteri
         * demek değildir; ham sayaç bu iki durumu ayırt edemez.
         *
         * Anahtarı olmayan olaylar (bu ölçüm eklenmeden önce yazılanlar)
         * sayıma GİRMEZ. Onları "bir kişi" saymak, bilinmeyeni bilinen gibi
         * göstermek olurdu.
         */
        public readonly int $uniqueVisitorCount,
        public readonly array $locations,
        public readonly array $qrCodes,
        public readonly string $generatedAt,
    ) {}

    /**
     * Karekodu tarayanların kaçı menüyü GERÇEKTEN açtı.
     *
     * İki olay aynı şey değildir: istek sunucuya ulaşmış olabilir ama sayfa
     * müşterinin cihazında açılmamış olabilir. Oran düştüğünde bakılacak yer
     * bellidir — bağlantı, yükleme süresi, ya da bozuk bir karekod.
     *
     * Tarama yoksa oran YOKTUR; sıfır döndürmek "kimse açmadı" der, oysa
     * doğrusu "kimse taramadı"dır.
     */
    public function openRate(): ?float
    {
        if ($this->qrResolveCount === 0) {
            return null;
        }

        return round($this->menuOpenCount / $this->qrResolveCount, 4);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'range' => $this->range,
            'qrResolveCount' => $this->qrResolveCount,
            'menuOpenCount' => $this->menuOpenCount,
            'uniqueVisitorCount' => $this->uniqueVisitorCount,
            'openRate' => $this->openRate(),
            'locations' => array_map(
                static fn (AnalyticsBreakdownRow $row): array => $row->toArray(),
                $this->locations,
            ),
            'qrCodes' => array_map(
                static fn (AnalyticsBreakdownRow $row): array => $row->toArray(),
                $this->qrCodes,
            ),
            'generatedAt' => $this->generatedAt,
        ];
    }
}
