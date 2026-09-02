<?php

declare(strict_types=1);

namespace App\Application\Analytics\Port;

use App\Application\Analytics\Dto\AnalyticsSummary;
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
}
