<?php

declare(strict_types=1);

namespace App\Application\Analytics\Dto;

/**
 * Saat × gün ısı haritasının bir hücresi — `docs/109` §1 (Insights).
 *
 * Sahibin bu hücreden çıkardığı karar somut: personeli hangi saate koyacağı,
 * mutfağın hangi saate hazırlanacağı. "Günde 30 tarama" bu kararı vermez;
 * "cumartesi 13:00'te 30 tarama" verir.
 *
 * Hem gün hem saat ŞUBENİN saat dilimindedir. UTC ile çizilen bir ısı
 * haritası İstanbul'un öğle yoğunluğunu sabaha taşır ve vardiya yanlış
 * kurulur.
 */
final class AnalyticsHourCell
{
    /**
     * @param  int  $weekday  ISO-8601 hafta günü: pazartesi 1 … pazar 7.
     * @param  int  $hour  Yerel saat, 0–23.
     */
    public function __construct(
        public readonly int $weekday,
        public readonly int $hour,
        public readonly int $qrResolveCount,
    ) {}

    /**
     * @return array{weekday: int, hour: int, qrResolveCount: int}
     */
    public function toArray(): array
    {
        return [
            'weekday' => $this->weekday,
            'hour' => $this->hour,
            'qrResolveCount' => $this->qrResolveCount,
        ];
    }
}
