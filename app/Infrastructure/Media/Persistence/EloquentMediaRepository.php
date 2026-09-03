<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Persistence;

use App\Application\Media\Dto\GeneratedRendition;
use App\Application\Media\Dto\MediaAssetSummary;
use App\Application\Media\Dto\MediaIntake;
use App\Application\Media\Dto\ProcessableMediaAsset;
use App\Application\Media\Dto\ScannableMediaAsset;
use App\Application\Media\Port\MediaRepositoryPort;
use App\Domain\Media\LifecycleStatus;
use App\Domain\Media\MediaAssetStatus;
use App\Models\MediaAsset;
use App\Support\Media\RenditionUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class EloquentMediaRepository implements MediaRepositoryPort
{
    private const QUARANTINE_DISK = 'local';

    /**
     * Boru hattı sürümü: kırpma, ölçekleme ve kodlama kurallarının bu
     * bileşimi. Kural değişince artar ve eski türevler toplu olarak
     * yeniden üretilebilir hâle gelir.
     */
    private const PIPELINE_VERSION = 'gd-1';

    public function intakeToQuarantine(int $workspaceId, MediaIntake $intake): MediaAssetSummary
    {
        $quarantineName = Str::uuid()->toString();
        $diskPath = "quarantine/{$workspaceId}/{$quarantineName}";

        $stream = fopen($intake->temporaryPath, 'rb');

        if ($stream === false) {
            throw new RuntimeException("Unable to open temporary media file at [{$intake->temporaryPath}].");
        }

        try {
            $put = Storage::disk(self::QUARANTINE_DISK)->put($diskPath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($put === false) {
            throw new RuntimeException("Unable to write media file to quarantine at [{$diskPath}].");
        }

        // Aslın parmak izi ALINDIĞI ANDA — sonradan "değişmedi" iddiasının
        // tek kanıtı budur (`docs/49` Faz 3 madde 1).
        $checksum = (string) hash_file('sha256', $intake->temporaryPath);

        try {
            $asset = MediaAsset::query()->create([
                'workspace_id' => $workspaceId,
                'disk_path' => $diskPath,
                'original_checksum_sha256' => $checksum,
                'original_name' => $intake->originalName,
                'mime_type' => $intake->detectedMimeType,
                'size_bytes' => $intake->sizeBytes,
                'alt_text' => $intake->altText,
                'slot' => $intake->slot,
                'idempotency_key' => $intake->idempotencyKey,
                'status' => MediaAssetStatus::Quarantined->value,
            ]);
        } catch (Throwable $exception) {
            Storage::disk(self::QUARANTINE_DISK)->delete($diskPath);

            throw $exception;
        }

        return $this->toSummary($asset);
    }

    public function findByIdempotencyKey(int $workspaceId, string $key): ?MediaAssetSummary
    {
        $asset = MediaAsset::query()
            ->where('workspace_id', $workspaceId)
            ->where('idempotency_key', $key)
            ->first();

        return $asset === null ? null : $this->toSummary($asset);
    }

    public function listForWorkspace(int $workspaceId): array
    {
        return MediaAsset::query()
            ->where('workspace_id', $workspaceId)
            ->orderBy('id')
            ->get()
            ->map(fn (MediaAsset $asset) => $this->toSummary($asset))
            ->all();
    }

    public function find(int $id): ?MediaAssetSummary
    {
        $asset = MediaAsset::query()->find($id);

        return $asset === null ? null : $this->toSummary($asset);
    }

    public function delete(int $id): void
    {
        $asset = MediaAsset::query()->find($id);

        if ($asset === null) {
            return;
        }

        /*
            ÇÖP, SİLME DEĞİLDİR (`docs/49` Faz 5 madde 3). Dosya diskte kalır;
            satır yaşam döngüsünde `trashed` olur ve listeden düşer. Yanlış
            silinen bir fotoğraf `restore` ile geri gelir; süresi dolan çöpü
            `media:purge-trash` kalıcı temizler. 2026-09-04'e kadar burada
            dosya hemen siliniyordu — "sildim, geri alamıyorum".
        */
        $asset->forceFill(['lifecycle_status' => LifecycleStatus::Trashed->value])->save();
        $asset->delete();
    }

    public function listTrashed(int $workspaceId): array
    {
        return MediaAsset::onlyTrashed()
            ->where('workspace_id', $workspaceId)
            ->where('lifecycle_status', LifecycleStatus::Trashed->value)
            ->orderByDesc('deleted_at')
            ->get()
            ->map(fn (MediaAsset $asset) => $this->toSummary($asset))
            ->all();
    }

    public function restore(int $workspaceId, int $assetId): bool
    {
        $asset = MediaAsset::onlyTrashed()
            ->where('id', $assetId)
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($asset === null) {
            return false;
        }

        $asset->forceFill(['lifecycle_status' => LifecycleStatus::Active->value])->save();
        $asset->restore();

        return true;
    }

    public function purgeTrash(int $olderThanDays, ?int $workspaceId = null): int
    {
        $rows = MediaAsset::onlyTrashed()
            ->when($workspaceId !== null, fn ($query) => $query->where('workspace_id', $workspaceId))
            ->where('lifecycle_status', LifecycleStatus::Trashed->value)
            ->where('deleted_at', '<', now()->subDays($olderThanDays))
            ->get();

        $purged = 0;

        foreach ($rows as $asset) {
            // Yayında kullanılan bir görsel çöpte bile purge edilmez: yayın
            // snapshot'ı onu gösteriyor olabilir (`docs/76` kriter 4).
            if ($this->isUsedByPublication((int) $asset->workspace_id, (int) $asset->getKey())) {
                continue;
            }

            // Dosya gitmediyse satır da gitmez: yetim dosyanın izi kaybolmasın.
            if (Storage::disk(self::QUARANTINE_DISK)->delete((string) $asset->disk_path) === false) {
                continue;
            }

            $storageKeys = DB::table('media_blobs')
                ->join('media_renditions', 'media_renditions.media_blob_id', '=', 'media_blobs.id')
                ->join('media_versions', 'media_versions.id', '=', 'media_renditions.media_version_id')
                ->where('media_versions.media_asset_id', $asset->getKey())
                ->pluck('media_blobs.storage_key');

            foreach ($storageKeys as $storageKey) {
                Storage::disk(self::QUARANTINE_DISK)->delete((string) $storageKey);
            }

            $asset->forceFill(['lifecycle_status' => LifecycleStatus::Purged->value])->save();
            $asset->forceDelete();
            $purged++;
        }

        return $purged;
    }

    public function usagesFor(int $workspaceId, int $assetId): array
    {
        $rows = DB::table('media_usages')
            ->where('workspace_id', $workspaceId)
            ->where('media_asset_id', $assetId)
            ->orderBy('id')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $label = match ((string) $row->entity_type) {
                'menu_item' => (string) (DB::table('menu_items')
                    ->join('products', 'products.id', '=', 'menu_items.product_id')
                    ->where('menu_items.id', (int) $row->entity_id)
                    ->value('products.name') ?? "#{$row->entity_id}"),
                'brand' => (string) (DB::table('brands')->where('id', (int) $row->entity_id)->value('name') ?? "#{$row->entity_id}"),
                default => (string) $row->entity_type.' #'.$row->entity_id,
            };

            $out[] = [
                'entityType' => (string) $row->entity_type,
                'entityId' => (int) $row->entity_id,
                'slot' => (string) $row->slot,
                'label' => $label,
                'published' => $row->publication_id !== null,
            ];
        }

        return $out;
    }

    public function detachDraftUsages(int $workspaceId, int $assetId): int
    {
        return DB::table('media_usages')
            ->where('workspace_id', $workspaceId)
            ->where('media_asset_id', $assetId)
            ->whereNull('publication_id')
            ->delete();
    }

    public function claimQuarantinedForScanning(int $workspaceId, int $assetId): ?ScannableMediaAsset
    {
        $asset = MediaAsset::query()
            ->where('id', $assetId)
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($asset === null) {
            return null;
        }

        $claimed = MediaAsset::query()
            ->where('id', $assetId)
            ->where('workspace_id', $workspaceId)
            ->where('status', MediaAssetStatus::Quarantined->value)
            ->update(['status' => MediaAssetStatus::Scanning->value]);

        if ($claimed === 0) {
            return null;
        }

        return new ScannableMediaAsset(
            id: (int) $asset->getKey(),
            workspaceId: (int) $asset->workspace_id,
            diskPath: Storage::disk(self::QUARANTINE_DISK)->path((string) $asset->disk_path),
        );
    }

    public function markRejectedIfScanning(int $workspaceId, int $assetId): void
    {
        MediaAsset::query()
            ->where('id', $assetId)
            ->where('workspace_id', $workspaceId)
            ->where('status', MediaAssetStatus::Scanning->value)
            ->update(['status' => MediaAssetStatus::Rejected->value]);
    }

    public function markAcceptedIfScanning(int $workspaceId, int $assetId): void
    {
        MediaAsset::query()
            ->where('id', $assetId)
            ->where('workspace_id', $workspaceId)
            ->where('status', MediaAssetStatus::Scanning->value)
            ->update(['status' => MediaAssetStatus::Accepted->value]);
    }

    public function claimAcceptedForProcessing(int $workspaceId, int $assetId): ?ProcessableMediaAsset
    {
        $asset = MediaAsset::query()
            ->where('id', $assetId)
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($asset === null) {
            return null;
        }

        $claimed = MediaAsset::query()
            ->where('id', $assetId)
            ->where('workspace_id', $workspaceId)
            ->where('status', MediaAssetStatus::Accepted->value)
            ->update(['status' => MediaAssetStatus::Processing->value]);

        if ($claimed === 0) {
            return null;
        }

        return new ProcessableMediaAsset(
            id: (int) $asset->getKey(),
            workspaceId: (int) $asset->workspace_id,
            diskPath: Storage::disk(self::QUARANTINE_DISK)->path((string) $asset->disk_path),
            slot: (string) $asset->slot,
        );
    }

    public function claimReadyForReprocessing(int $workspaceId, int $assetId): ?ProcessableMediaAsset
    {
        $asset = MediaAsset::query()->where('id', $assetId)->where('workspace_id', $workspaceId)->first();

        if ($asset === null) {
            return null;
        }

        $claimed = MediaAsset::query()
            ->where('id', $assetId)
            ->where('workspace_id', $workspaceId)
            ->where('status', MediaAssetStatus::Ready->value)
            ->update(['status' => MediaAssetStatus::Processing->value]);

        if ($claimed === 0) {
            return null;
        }

        return new ProcessableMediaAsset(
            id: (int) $asset->getKey(),
            workspaceId: (int) $asset->workspace_id,
            diskPath: Storage::disk(self::QUARANTINE_DISK)->path((string) $asset->disk_path),
            slot: (string) $asset->slot,
        );
    }

    public function versionsFor(int $workspaceId, int $assetId): array
    {
        $owned = MediaAsset::query()->where('id', $assetId)->where('workspace_id', $workspaceId)->exists();

        if (! $owned) {
            return [];
        }

        return DB::table('media_versions as v')
            ->leftJoin('media_renditions as r', 'r.media_version_id', '=', 'v.id')
            ->where('v.media_asset_id', $assetId)
            ->groupBy('v.id', 'v.version_number', 'v.created_by_kind', 'v.created_at')
            ->orderByDesc('v.version_number')
            ->get(['v.id', 'v.version_number', 'v.created_by_kind', 'v.created_at', DB::raw('count(r.id) as rendition_count')])
            ->map(static fn (object $row): array => [
                'number' => (int) $row->version_number,
                'id' => (int) $row->id,
                'createdBy' => (string) $row->created_by_kind,
                'createdAt' => (string) $row->created_at,
                'renditionCount' => (int) $row->rendition_count,
            ])
            ->all();
    }

    public function restoreVersion(int $workspaceId, int $assetId, int $versionNumber): ?int
    {
        $owned = MediaAsset::query()->where('id', $assetId)->where('workspace_id', $workspaceId)->exists();

        if (! $owned) {
            return null;
        }

        $source = DB::table('media_versions')
            ->where('media_asset_id', $assetId)
            ->where('version_number', $versionNumber)
            ->first();

        if ($source === null) {
            return null;
        }

        /*
            GERİ ALMA = YENİ SÜRÜM. Eski satırı "geçerli" işaretlemek yerine
            onun rendition'ları yeni bir sürüme kopyalanır: blob'lar aynıdır
            (adres parmak izi de aynı kalır, önbellek bozulmaz), yalnız sürüm
            satırları yenidir. Geçmiş asla yeniden yazılmaz — bir yayın
            snapshot'ı hâlâ v2'yi gösteriyorsa v2 orada durur.
        */
        return DB::transaction(function () use ($assetId, $source): int {
            $next = ((int) DB::table('media_versions')->where('media_asset_id', $assetId)->max('version_number')) + 1;

            $versionId = (int) DB::table('media_versions')->insertGetId([
                'media_asset_id' => $assetId,
                'media_blob_id' => (int) $source->media_blob_id,
                'version_number' => $next,
                'created_by_kind' => 'restore:v'.$source->version_number,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (DB::table('media_renditions')->where('media_version_id', $source->id)->get() as $rendition) {
                DB::table('media_renditions')->insert([
                    'media_version_id' => $versionId,
                    'media_blob_id' => (int) $rendition->media_blob_id,
                    'profile' => (string) $rendition->profile,
                    'width' => (int) $rendition->width,
                    'height' => (int) $rendition->height,
                    'format' => (string) $rendition->format,
                    'pipeline_version' => (string) $rendition->pipeline_version,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $versionId;
        });
    }

    public function markReadyIfProcessing(int $workspaceId, int $assetId): void
    {
        MediaAsset::query()
            ->where('id', $assetId)
            ->where('workspace_id', $workspaceId)
            ->where('status', MediaAssetStatus::Processing->value)
            ->update(['status' => MediaAssetStatus::Ready->value]);
    }

    public function markFailedIfProcessing(int $workspaceId, int $assetId): void
    {
        MediaAsset::query()
            ->where('id', $assetId)
            ->where('workspace_id', $workspaceId)
            ->where('status', MediaAssetStatus::Processing->value)
            ->update(['status' => MediaAssetStatus::Failed->value]);
    }

    public function openProcessingJob(int $workspaceId, int $assetId): int
    {
        // Deneme SONUCUNDAN önce yazılır: süreç ortasında ölen bir iş de
        // görünür kalmalı (`docs/76`).
        return (int) DB::table('media_processing_jobs')->insertGetId([
            'workspace_id' => $workspaceId,
            'media_asset_id' => $assetId,
            'kind' => 'rendition',
            'state' => 'running',
            'attempts' => 1,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function openScanJob(int $workspaceId, int $assetId): int
    {
        return (int) DB::table('media_processing_jobs')->insertGetId([
            'workspace_id' => $workspaceId,
            'media_asset_id' => $assetId,
            'kind' => 'scan',
            'state' => 'running',
            'attempts' => 1,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function closeScanJobAsCompleted(int $jobId): void
    {
        DB::table('media_processing_jobs')->where('id', $jobId)->update([
            'state' => 'succeeded',
            'failure_reason' => null,
            'finished_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function closeScanJobAsHeld(int $jobId, string $reason): void
    {
        // `held`, `failed`ten AYRI bir durumdur: dosyada bir sorun
        // bulunmadı, tarayıcı konuşamadı. İkisini aynı kelimeyle anlatmak
        // sahibi "dosyam bozuk" sanmaya iter.
        DB::table('media_processing_jobs')->where('id', $jobId)->update([
            'state' => 'held',
            'failure_reason' => $reason,
            'finished_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Bu varlığı YAYINLANMIŞ bir menüye bağlayan kullanım var mı?
     *
     * Yayın, sahibin onayladığı donmuş hâldir; panelden yapılan bir
     * temizlik onu misafirin gözü önünde bozamaz (`docs/76`, kriter 4).
     */
    public function isUsedByPublication(int $workspaceId, int $assetId): bool
    {
        return DB::table('media_usages')
            ->where('workspace_id', $workspaceId)
            ->where('media_asset_id', $assetId)
            ->whereNotNull('publication_id')
            ->exists();
    }

    public function closeProcessingJobAsSucceeded(int $jobId): void
    {
        DB::table('media_processing_jobs')->where('id', $jobId)->update([
            'state' => 'succeeded',
            'failure_reason' => null,
            'finished_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function closeProcessingJobAsFailed(int $jobId, string $reason): void
    {
        DB::table('media_processing_jobs')->where('id', $jobId)->update([
            'state' => 'failed',
            'failure_reason' => $reason,
            'finished_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function persistRenditions(int $workspaceId, int $assetId, array $renditions, ?string $lqip = null): int
    {
        // Türev baytları ÖNCE diske yazılır, sonra satırlar açılır. Ters
        // sırada bir çökme, var olmayan dosyaya işaret eden bir kayıt
        // bırakırdı — ve o kayıt misafire kırık bir görsel gösterirdi.
        if ($renditions === []) {
            // Türev üretmemiş bir başarı, ORKESTRASYON için geçerli bir
            // durumdur (yerine geçen bir işleyici olabilir); burada yeni
            // bir sürüm açmak, boş bir sürüm yaratmak olurdu.
            return 0;
        }

        $written = [];

        try {
            foreach ($renditions as $rendition) {
                $checksum = hash('sha256', $rendition->bytes);
                $storageKey = "renditions/{$workspaceId}/{$assetId}/{$rendition->profile}-{$checksum}.{$rendition->format}";

                if (Storage::disk(self::QUARANTINE_DISK)->put($storageKey, $rendition->bytes) === false) {
                    throw new RuntimeException("Unable to write rendition to [{$storageKey}].");
                }

                $written[] = [$rendition, $storageKey, $checksum];
            }

            return DB::transaction(function () use ($workspaceId, $assetId, $written, $lqip): int {
                $nextVersion = ((int) DB::table('media_versions')
                    ->where('media_asset_id', $assetId)
                    ->max('version_number')) + 1;

                // Sürümün kendi kaynağı ilk türevin blob'udur: bu tabloda
                // `media_blob_id` zorunlu ve en büyük türev, sürümün
                // temsilcisi olarak en anlamlı olandır.
                [$largest, $largestKey, $largestChecksum] = $this->largestOf($written);

                $sourceBlobId = $this->insertBlob($workspaceId, $largestKey, $largestChecksum, $largest);

                $versionId = (int) DB::table('media_versions')->insertGetId([
                    'media_asset_id' => $assetId,
                    'media_blob_id' => $sourceBlobId,
                    'version_number' => $nextVersion,
                    // İlk sürüm yüklemeden doğar; sonrakiler yeniden üretimdir
                    // (asıl aynı, boru hattı yeni) — geçmişte hangisi olduğu okunur.
                    'created_by_kind' => $nextVersion === 1 ? 'upload' : 'reprocess',
                    'lqip' => $lqip,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($written as [$rendition, $storageKey, $checksum]) {
                    $blobId = $storageKey === $largestKey
                        ? $sourceBlobId
                        : $this->insertBlob($workspaceId, $storageKey, $checksum, $rendition);

                    DB::table('media_renditions')->insert([
                        'media_version_id' => $versionId,
                        'media_blob_id' => $blobId,
                        'profile' => $rendition->profile,
                        'width' => $rendition->width,
                        'height' => $rendition->height,
                        'format' => $rendition->format,
                        // Algoritma değişince toplu yeniden üretim yapılabilsin
                        // diye HANGİ boru hattının ürettiği kaydedilir.
                        'pipeline_version' => self::PIPELINE_VERSION,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                return $versionId;
            });
        } catch (Throwable $exception) {
            foreach ($written as [, $storageKey]) {
                // Başka bir sürüm bu dosyayı zaten kullanıyorsa silinmez:
                // temizlik, çalışan bir yayını bozamaz.
                $shared = DB::table('media_blobs')
                    ->where('workspace_id', $workspaceId)
                    ->where('storage_key', $storageKey)
                    ->exists();

                if (! $shared) {
                    Storage::disk(self::QUARANTINE_DISK)->delete($storageKey);
                }
            }

            throw $exception;
        }
    }

    /**
     * @param  list<array{0:GeneratedRendition,1:string,2:string}>  $written
     * @return array{0:GeneratedRendition,1:string,2:string}
     */
    private function largestOf(array $written): array
    {
        $largest = $written[0];

        foreach ($written as $candidate) {
            if ($candidate[0]->width > $largest[0]->width) {
                $largest = $candidate;
            }
        }

        return $largest;
    }

    private function insertBlob(int $workspaceId, string $storageKey, string $checksum, GeneratedRendition $rendition): int
    {
        // AYNI görselin yeniden işlenmesi aynı baytları üretir, dolayısıyla
        // aynı depolama anahtarını. `storage_key` tekildir; körü körüne
        // eklemek, değişmemiş bir fotoğrafın yeniden işlenmesini
        // başarısız kılardı — oysa bu tamamen normal bir iştir (algoritma
        // güncellenince toplu yeniden üretim).
        //
        // Kiracı İÇİNDE tekilleştirme yapılır; kiracılar ARASI bilerek
        // yapılmaz (silme, kota ve "başka tenant bu dosyaya sahip mi"
        // sızıntısı karmaşıklaşır — göç dosyasındaki not).
        $existing = DB::table('media_blobs')
            ->where('workspace_id', $workspaceId)
            ->where('storage_key', $storageKey)
            ->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table('media_blobs')->insertGetId([
            'workspace_id' => $workspaceId,
            'storage_key' => $storageKey,
            'disk' => self::QUARANTINE_DISK,
            'mime_type' => $rendition->mimeType,
            'size_bytes' => strlen($rendition->bytes),
            'checksum_sha256' => $checksum,
            'width' => $rendition->width,
            'height' => $rendition->height,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function toSummary(MediaAsset $asset): MediaAssetSummary
    {
        return new MediaAssetSummary(
            id: (int) $asset->getKey(),
            workspaceId: (int) $asset->workspace_id,
            altText: (string) $asset->alt_text,
            slot: (string) $asset->slot,
            status: (string) $asset->status,
            statusReason: $this->latestBlockingReason((int) $asset->getKey()),
            duplicateOfId: $this->duplicateOf($asset),
            previewUrl: $this->previewUrl($asset),
            usageCount: (int) DB::table('media_usages')
                ->where('media_asset_id', $asset->getKey())
                ->whereNull('publication_id')
                ->count(),
            versionCount: (int) DB::table('media_versions')->where('media_asset_id', $asset->getKey())->count(),
            originalName: (string) $asset->original_name,
            sizeBytes: (int) $asset->size_bytes,
            createdAt: $asset->created_at?->toIso8601String(),
            lifecycle: (string) ($asset->lifecycle_status ?? 'draft'),
        );
    }

    /** Geçerli sürümün EN KÜÇÜK rendition'ı — kütüphane küçük resmi için yeter (`docs/49` Faz 4). */
    private function previewUrl(MediaAsset $asset): ?string
    {
        $versionId = DB::table('media_versions')
            ->where('media_asset_id', $asset->getKey())
            ->orderByDesc('version_number')
            ->value('id');

        if ($versionId === null) {
            return null;
        }

        $row = DB::table('media_renditions')
            ->join('media_blobs', 'media_blobs.id', '=', 'media_renditions.media_blob_id')
            ->where('media_renditions.media_version_id', $versionId)
            ->orderBy('media_renditions.width')
            ->first(['media_renditions.id', 'media_renditions.format', 'media_blobs.checksum_sha256']);

        return $row === null ? null : RenditionUrl::for((int) $row->id, (string) $row->checksum_sha256, (string) $row->format);
    }

    /**
     * Aynı kiracıda aynı parmak izli DAHA ESKİ bir varlık — "bu fotoğraf
     * zaten var" (`docs/49` Faz 3 madde 4). Kiracılar arası bakılmaz: başka
     * bir restoranın aynı dosyaya sahip olduğunu söylemek bile sızıntıdır.
     */
    private function duplicateOf(MediaAsset $asset): ?int
    {
        $checksum = $asset->original_checksum_sha256;

        if ($checksum === null || $checksum === '') {
            return null;
        }

        $earlier = MediaAsset::query()
            ->where('workspace_id', (int) $asset->workspace_id)
            ->where('original_checksum_sha256', (string) $checksum)
            ->where('id', '<', (int) $asset->getKey())
            ->orderBy('id')
            ->value('id');

        return $earlier === null ? null : (int) $earlier;
    }

    /**
     * En son işin sebebi — yalnız bir şey ters gittiğinde.
     *
     * Sorunsuz bir dosyaya sebep yazmak gürültüdür; sahip her satırda bir
     * açıklama görmeye başlarsa gerçek uyarıyı okumaz.
     */
    private function latestBlockingReason(int $assetId): ?string
    {
        $reason = DB::table('media_processing_jobs')
            ->where('media_asset_id', $assetId)
            ->whereIn('state', ['held', 'failed'])
            ->orderByDesc('id')
            ->value('failure_reason');

        return ($reason === null || (string) $reason === '') ? null : (string) $reason;
    }
}
