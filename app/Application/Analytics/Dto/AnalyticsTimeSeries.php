<?php

declare(strict_types=1);

namespace App\Application\Analytics\Dto;

/**
 * Insights ekranının zaman serisi — `docs/109` §1, §6.5.
 *
 * Tek bir yanıtta dört soru birden cevaplanır çünkü ekran dördünü BİRLİKTE
 * gösterir: hangi gün, geçen döneme göre nasıl, hangi saatte, hangi şubede.
 * Dört ayrı istek, dört ayrı yükleniyor durumu ve dört ayrı hata yolu
 * demekti; sahibin bir bakışta okuduğu ekran parça parça belirirdi.
 *
 * `state` `menu-engineering` ile AYNI kelimeleri kullanır (`ready` /
 * `not_enough_data`, `threshold`, gözlenen sayı). İki rapor aynı ekranda
 * yan yana duruyor; farklı kelimelerle aynı şeyi söylemek, sahibi iki ayrı
 * kural olduğunu sanmaya iter.
 */
final class AnalyticsTimeSeries
{
    public const STATE_READY = 'ready';

    public const STATE_NOT_ENOUGH_DATA = 'not_enough_data';

    /**
     * @param  list<AnalyticsDailyBucket>  $buckets
     * @param  list<AnalyticsHourCell>  $hourly
     * @param  list<AnalyticsLocationShare>  $locationShare
     */
    public function __construct(
        public readonly string $range,
        public readonly string $state,
        public readonly int $threshold,
        /** Pencerede sayılan FARKLI ziyaretçi — eşiğin karşılaştırıldığı sayı. */
        public readonly int $observedVisitors,
        /** Kovaların çizildiği saat dilimi; şubenin, sunucunun değil. */
        public readonly string $timezone,
        public readonly array $buckets,
        public readonly ?AnalyticsComparison $comparison,
        public readonly array $hourly,
        /**
         * Tek ziyaretçiye dayandığı için yayımlanmayan hücre sayısı.
         *
         * Sessizce düşürülmez: ekran "o saatte kimse yoktu" derse bu
         * yanlıştır. Kaç hücrenin gizlendiğini söylemek, gizlemenin kendisi
         * kadar önemlidir.
         */
        public readonly int $suppressedHourCells,
        public readonly array $locationShare,
        /** Pay her zaman markanın tamamından okunur; kapsam açıkça yazılır. */
        public readonly string $locationShareScope,
        public readonly string $generatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'range' => $this->range,
            'state' => $this->state,
            'threshold' => $this->threshold,
            'observedVisitors' => $this->observedVisitors,
            'timezone' => $this->timezone,
            'buckets' => array_map(
                static fn (AnalyticsDailyBucket $bucket): array => $bucket->toArray(),
                $this->buckets,
            ),
            'comparison' => $this->comparison?->toArray(),
            'hourly' => array_map(
                static fn (AnalyticsHourCell $cell): array => $cell->toArray(),
                $this->hourly,
            ),
            'suppressedHourCells' => $this->suppressedHourCells,
            'locationShare' => array_map(
                static fn (AnalyticsLocationShare $row): array => $row->toArray(),
                $this->locationShare,
            ),
            'locationShareScope' => $this->locationShareScope,
            'generatedAt' => $this->generatedAt,
        ];
    }
}
