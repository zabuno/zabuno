<?php

declare(strict_types=1);

namespace App\Application\Analytics\Dto;

/**
 * Bir önceki dönemle karşılaştırma — `docs/109` §1 (Insights).
 *
 * Tek başına bir sayı iyi mi kötü mü söylemez: "bu hafta 214 tarama" ancak
 * geçen haftanın yanında anlam kazanır. Sahibin ilk sorusu zaten budur ve
 * bugüne kadar cevabı yoktu — iki ayrı istek atıp kafadan çıkarmak
 * gerekiyordu.
 */
final class AnalyticsComparison
{
    /** Aynı uzunluktaki bir önceki pencere (7 gün ↔ önceki 7 gün). */
    public const BASIS_PREVIOUS_PERIOD = 'previous_period';

    /**
     * Geçen haftanın AYNI GÜNÜ, aynı saate kadar.
     *
     * "Bugün" için önceki günü almak yanıltıcıdır: bir restoranda cumartesi
     * ile salı aynı işletme değildir. Kaynağın kendi cümlesi de bunu söyler
     * ("%12 · geçen perşembe").
     */
    public const BASIS_SAME_WEEKDAY_LAST_WEEK = 'same_weekday_last_week';

    public function __construct(
        public readonly string $basis,
        public readonly int $currentQrResolveCount,
        public readonly int $previousQrResolveCount,
        public readonly string $previousStart,
        public readonly string $previousEnd,
    ) {}

    /**
     * Oransal değişim. Önceki pencere boşsa oran YOKTUR.
     *
     * Sıfırdan yüzde artış hesaplanamaz: bölen sıfırdır. "%100 arttı" demek
     * uydurmadır, "%0" demek ise düpedüz yanlıştır. `openRate` ile aynı
     * disiplin — hesaplanamayan oran `null`'dur.
     */
    public function deltaRatio(): ?float
    {
        if ($this->previousQrResolveCount === 0) {
            return null;
        }

        return round(
            ($this->currentQrResolveCount - $this->previousQrResolveCount) / $this->previousQrResolveCount,
            4,
        );
    }

    /**
     * @return array{basis: string, currentQrResolveCount: int, previousQrResolveCount: int, deltaRatio: float|null, previousStart: string, previousEnd: string}
     */
    public function toArray(): array
    {
        return [
            'basis' => $this->basis,
            'currentQrResolveCount' => $this->currentQrResolveCount,
            'previousQrResolveCount' => $this->previousQrResolveCount,
            'deltaRatio' => $this->deltaRatio(),
            'previousStart' => $this->previousStart,
            'previousEnd' => $this->previousEnd,
        ];
    }
}
