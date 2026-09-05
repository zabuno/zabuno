<?php

declare(strict_types=1);

namespace App\Application\Analytics\Dto;

/**
 * Bir GÜNÜN kovası — `docs/109` §1 (Insights), §6.5.
 *
 * Aralık toplamı ("son 7 günde 214 tarama") bir haftanın şeklini gizler:
 * salı günü hiç tarama olmadığını, cumartesinin haftanın yarısını taşıdığını
 * o sayı söylemez. Kova, toplamın sakladığı şeydir.
 *
 * İki sayı BİRLİKTE durur çünkü kaynağın grafiği ikisini üst üste çiziyor:
 * çubuk tarama, çizgi menü açılışı. Aradaki fark bir teşhistir — tarama çok
 * ama açılış az ise sorun karekodda değil, menünün yüklenmesindedir.
 */
final class AnalyticsDailyBucket
{
    /** @param string $date Şubenin saat dilimindeki takvim günü (Y-m-d). */
    public function __construct(
        public readonly string $date,
        public readonly int $qrResolveCount,
        public readonly int $menuOpenCount,
    ) {}

    /**
     * @return array{date: string, qrResolveCount: int, menuOpenCount: int}
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'qrResolveCount' => $this->qrResolveCount,
            'menuOpenCount' => $this->menuOpenCount,
        ];
    }
}
