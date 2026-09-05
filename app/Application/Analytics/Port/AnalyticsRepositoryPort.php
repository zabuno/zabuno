<?php

declare(strict_types=1);

namespace App\Application\Analytics\Port;

use App\Application\Analytics\Dto\AnalyticsSummary;
use App\Application\Analytics\Dto\AnalyticsTimeSeries;
use App\Domain\Analytics\AnalyticsEventType;
use Illuminate\Support\Carbon;

interface AnalyticsRepositoryPort
{
    public function record(
        int $workspaceId,
        int $locationId,
        /** Kalıcı adresten gelen misafirin karekodu YOKTUR (`docs/84`). */
        ?int $qrCodeId,
        int $menuId,
        AnalyticsEventType $eventType,
        Carbon $occurredAt,
        /** Türetilmiş, geri çevrilemez ziyaretçi özeti; bilinmiyorsa null. */
        ?string $visitorKey = null,
        ?int $menuItemId = null,
        ?string $searchTerm = null,
    ): void;

    /**
     * Aynı ziyaretçi, aynı ürün, aynı gün: ZATEN sayıldı mı?
     *
     * Sayılan şey İLGİ, kaydırma alışkanlığı değil (`docs/84`).
     */
    public function itemViewAlreadyCounted(int $workspaceId, int $menuItemId, string $visitorKey, Carbon $on): bool;

    /**
     * Menü mühendisliği raporu: ürün başına FARKLI ziyaretçi sayısı.
     *
     * Ham vuruş değil farklı ziyaretçi sayılır — hem daha anlamlıdır hem de
     * herkese açık uçtan gelen ucuz şişirmeye dayanıklıdır.
     *
     * @return array<int, int> menü satırı kimliği → farklı ziyaretçi sayısı
     */
    public function itemViewersByMenuItem(int $workspaceId, string $range, Carbon $now): array;

    /** @param  int|null  $locationId  `null` ise çalışma alanının tamamı. */
    public function summarize(
        int $workspaceId,
        ?int $locationId,
        string $range,
        Carbon $now,
    ): AnalyticsSummary;

    /**
     * Zaman serisi: günlük kovalar, önceki dönem, saat ısı haritası, şube payı.
     *
     * `summarize` yalnız ARALIK TOPLAMI üretiyor ve o toplam bir haftanın
     * şeklini gizliyor: hangi gün çöktü, hangi saatte yoğunlaştı, geçen
     * haftaya göre nasıl (`docs/109` §1, Insights). Ayrı bir metot olmasının
     * sebebi kapsam: özet uçları bugün panonun sayaçlarını besliyor ve
     * onların yanıtına yeni alanlar eklemek, o yanıtı okuyan her istemciyi
     * etkilerdi.
     *
     * @param  int|null  $locationId  `null` ise çalışma alanının tamamı.
     */
    public function timeSeries(
        int $workspaceId,
        ?int $locationId,
        string $range,
        Carbon $now,
    ): AnalyticsTimeSeries;
}
