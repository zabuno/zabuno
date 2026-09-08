<?php

declare(strict_types=1);

namespace App\Application\DataRights\Port;

use App\Application\DataRights\Dto\DataRequestRow;
use App\Domain\DataRights\DataRequestKind;
use App\Domain\DataRights\DataRequestState;

/**
 * Veri hakları defteri — FF-226.
 *
 * Port, çünkü defterin NEREDE tutulduğu bir altyapı kararıdır; uygulamanın
 * bilmesi gereken tek şey, bir talebin kaydedildiği ve okunabildiği.
 */
interface DataRequestRepositoryPort
{
    /**
     * @param  list<string>  $scopeSections
     */
    public function open(
        int $workspaceId,
        DataRequestKind $kind,
        int $requestedByUserId,
        array $scopeSections,
        ?string $scheduledFor = null,
    ): DataRequestRow;

    public function find(int $id): ?DataRequestRow;

    /**
     * Bir çalışma alanının talepleri, en yeni önce.
     *
     * @return list<DataRequestRow>
     */
    public function forWorkspace(int $workspaceId, int $limit = 20): array;

    /**
     * Bu çalışma alanının o türden hâlâ AÇIK bir talebi var mı — ikinci bir
     * silme isteği ilkini gölgelememeli.
     */
    public function openRequest(int $workspaceId, DataRequestKind $kind): ?DataRequestRow;

    public function markState(int $id, DataRequestState $state): void;

    public function markExportReady(int $id, string $path, int $bytes, string $checksum, string $availableUntil): void;

    public function markFailed(int $id, string $reason): void;

    public function markCancelled(int $id, int $cancelledByUserId): void;

    /**
     * @param  array<string, int>  $deletedCounts
     */
    public function markCompleted(int $id, array $deletedCounts): void;

    /** `docs/93`: taşıyıcı yoksa damga atılmaz, kayıt yine ekranda durur. */
    public function recordNotification(int $id, string|false|null $outcome): void;

    /** Dosyanın diskteki yolu — yalnız indirme ve temizlik için. */
    public function artifactPath(int $id): ?string;

    /**
     * Süresi dolmuş, hâlâ `ready` görünen dışa aktarmalar.
     *
     * @return list<DataRequestRow>
     */
    public function expiredExports(): array;

    /**
     * Yürütme günü gelmiş, iptal edilmemiş silme talepleri.
     *
     * @return list<DataRequestRow>
     */
    public function dueErasures(): array;
}
