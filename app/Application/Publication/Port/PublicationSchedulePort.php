<?php

declare(strict_types=1);

namespace App\Application\Publication\Port;

use App\Application\Publication\Dto\ScheduledPublicationRecord;
use Carbon\CarbonInterface;

interface PublicationSchedulePort
{
    /**
     * Menü için bekleyen planı yazar.
     *
     * BİR MENÜNÜN AYNI ANDA TEK BEKLEYEN PLANI olur: sahip "bu gece 03:00"
     * dedikten sonra fikrini değiştirip "Pazartesi 09:00" derse, iki ayrı
     * yayın değil tek bir plan olmalıdır. Uygulama önceki bekleyen planı
     * iptal ederek yerine yenisini koyar.
     *
     * @param  array<string,mixed>  $snapshot
     * @param  list<int>  $visibleItemIds
     */
    public function schedule(
        int $workspaceId,
        int $menuId,
        int $locationId,
        CarbonInterface $scheduledFor,
        array $snapshot,
        array $visibleItemIds,
        ?int $brandId,
        int $scheduledByUserId,
    ): ScheduledPublicationRecord;

    public function pendingForMenu(int $workspaceId, int $menuId): ?ScheduledPublicationRecord;

    /** Bekleyen planı iptal eder; iptal edilecek plan yoksa `false`. */
    public function cancel(int $workspaceId, int $menuId, int $scheduleId): bool;

    /**
     * Vakti gelmiş bekleyen planları döndürür (en eski önce).
     *
     * @return list<ScheduledPublicationRecord>
     */
    public function due(CarbonInterface $now): array;

    /**
     * Kaydı `pending` → `publishing` yapar ve YALNIZ bu koşu sahiplendiyse
     * `true` döner. Aynı anda iki koşu varsa yalnız biri `true` alır.
     */
    public function claim(int $scheduleId): bool;

    public function markPublished(int $scheduleId, int $publicationId): void;

    public function markFailed(int $scheduleId): void;
}
