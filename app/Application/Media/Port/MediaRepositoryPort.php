<?php

declare(strict_types=1);

namespace App\Application\Media\Port;

use App\Application\Media\Dto\GeneratedRendition;
use App\Application\Media\Dto\MediaAssetSummary;
use App\Application\Media\Dto\MediaIntake;
use App\Application\Media\Dto\ProcessableMediaAsset;
use App\Application\Media\Dto\ScannableMediaAsset;

interface MediaRepositoryPort
{
    public function intakeToQuarantine(int $workspaceId, MediaIntake $intake): MediaAssetSummary;

    /**
     * @return list<MediaAssetSummary>
     */
    /** Aynı anahtarla daha önce alınmış varlık — yeniden deneme tekrar yaratmaz (`docs/49` Faz 2). */
    public function findByIdempotencyKey(int $workspaceId, string $key): ?MediaAssetSummary;

    public function listForWorkspace(int $workspaceId): array;

    public function find(int $id): ?MediaAssetSummary;

    public function delete(int $id): void;

    /**
     * Atomically claims the workspace's exact quarantined asset into
     * scanning. A non-quarantined or cross-workspace asset is a no-op.
     */
    public function claimQuarantinedForScanning(int $workspaceId, int $assetId): ?ScannableMediaAsset;

    public function markRejectedIfScanning(int $workspaceId, int $assetId): void;

    public function markAcceptedIfScanning(int $workspaceId, int $assetId): void;

    /**
     * Atomically claims the workspace's exact accepted asset into
     * processing. A non-accepted or cross-workspace asset is a no-op.
     */
    public function claimAcceptedForProcessing(int $workspaceId, int $assetId): ?ProcessableMediaAsset;

    public function markReadyIfProcessing(int $workspaceId, int $assetId): void;

    public function markFailedIfProcessing(int $workspaceId, int $assetId): void;

    /**
     * Bir işleme denemesini kaydeder ve kimliğini döner.
     *
     * Deneme, SONUCUNDAN önce yazılır: süreç ortasında ölen bir iş de
     * görünür kalmalı, yoksa sahip "hiç denenmedi" ile "denendi ve çöktü"
     * arasındaki farkı göremez (`docs/76`).
     */
    public function openProcessingJob(int $workspaceId, int $assetId): int;

    /**
     * Tarama denemesini kaydeder.
     *
     * Tarayıcı yoksa ürün bunu SAKLAMAZ: sahip "taranıyor" ile
     * "taranamıyor" arasındaki farkı görebilmeli (`docs/76`).
     */
    public function openScanJob(int $workspaceId, int $assetId): int;

    public function closeScanJobAsCompleted(int $jobId): void;

    public function closeScanJobAsHeld(int $jobId, string $reason): void;

    /** Bu varlığı yayınlanmış bir menüye bağlayan bir kullanım var mı? */
    public function isUsedByPublication(int $workspaceId, int $assetId): bool;

    public function closeProcessingJobAsSucceeded(int $jobId): void;

    public function closeProcessingJobAsFailed(int $jobId, string $reason): void;

    /**
     * İşlenmiş türevleri YENİ bir sürüm altında kalıcılaştırır.
     *
     * Sürüme bağlanır, varlığa değil: yayınlanmış bir menü, sonradan
     * düzenlenen bir fotoğrafı kendiliğinden göstermemeli.
     *
     * @param  list<GeneratedRendition>  $renditions
     * @return int Üretilen sürümün kimliği
     */
    public function persistRenditions(int $workspaceId, int $assetId, array $renditions): int;

    /**
     * HAZIR bir varlığı yeniden üretim için kilitler (ready → processing).
     * Asıl dosyaya dokunulmaz; sonuç YENİ bir sürüm olur (`docs/49` Faz 3).
     */
    public function claimReadyForReprocessing(int $workspaceId, int $assetId): ?ProcessableMediaAsset;

    /**
     * @return list<array{number:int, id:int, createdBy:string, createdAt:string, renditionCount:int}>
     */
    public function versionsFor(int $workspaceId, int $assetId): array;

    /**
     * Eski bir sürümü geri getirir — YENİ bir sürüm olarak. Sürüm geçmişi
     * append-only: "en büyük sürüm = geçerli" kuralı bozulmaz, hiçbir satır
     * silinmez. Sürüm yoksa null döner.
     */
    public function restoreVersion(int $workspaceId, int $assetId, int $versionNumber): ?int;
}
