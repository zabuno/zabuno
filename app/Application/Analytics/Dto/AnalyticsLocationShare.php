<?php

declare(strict_types=1);

namespace App\Application\Analytics\Dto;

/**
 * Şube payı — `docs/109` §1 (Insights, "şube halkası").
 *
 * Bu bir MARKA sorusudur: "bu şube markanın ne kadarı?". Bu yüzden pay
 * seçili şubeye göre süzülmez — süzülseydi halka her zaman %100 çizilir,
 * yani hiçbir şey söylemeyen bir daire olurdu.
 */
final class AnalyticsLocationShare
{
    public function __construct(
        public readonly int $id,
        public readonly string $label,
        public readonly int $qrResolveCount,
        /** Yüzde, iki basamağa yuvarlı. Marka toplamı sıfırsa hepsi 0.0'dır. */
        public readonly float $sharePercent,
    ) {}

    /**
     * @return array{id: int, label: string, qrResolveCount: int, sharePercent: float}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'qrResolveCount' => $this->qrResolveCount,
            'sharePercent' => $this->sharePercent,
        ];
    }
}
