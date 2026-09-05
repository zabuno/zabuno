<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Persistence;

use App\Application\Media\Dto\MediaBulkCandidate;
use App\Application\Media\Port\MediaBulkPort;
use App\Domain\Media\LifecycleStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * `MediaBulkPort`in Eloquent/SQL karşılığı.
 *
 * Tek bir performans kararı bu sınıfın biçimini belirler: kuru çalışma
 * DOSYA BAŞINA sorgu atmaz. "Yayında kullanılıyor mu" sorusu tek bir
 * `whereIn` ile bir kez sorulur ve sonuç bir kümede tutulur; 1.800
 * dosyalık bir kapsamda 1.800 sorgu, sahibi ekranın önünde bekletirdi.
 */
final class EloquentMediaBulk implements MediaBulkPort
{
    private const QUARANTINE_DISK = 'local';

    private const ASSETS = 'media_assets';

    private const OPERATIONS = 'media_bulk_operations';

    /** @return list<int> */
    public function idsForScope(int $workspaceId, string $scope, ?int $folderId, bool $trashed): array
    {
        $query = DB::table(self::ASSETS)->where('workspace_id', $workspaceId);

        $query = $trashed
            ? $query->whereNotNull('deleted_at')
            : $query->whereNull('deleted_at');

        if ($scope === 'folder') {
            /*
                Klasör kapsamı "bu klasör VE ALTI"dır. Klasör modeli iki
                seviyelidir (`docs/108` §3 madde 1), o yüzden özyineleme
                yok: klasörün kendisi ve doğrudan çocukları yeter. Üçüncü
                bir seviye hiç yaratılamıyor.
            */
            $folderIds = $folderId === null ? [] : [$folderId];

            if ($folderId !== null) {
                $children = DB::table('media_folders')
                    ->where('workspace_id', $workspaceId)
                    ->where('parent_id', $folderId)
                    ->pluck('id')
                    ->all();

                $folderIds = array_merge($folderIds, array_map('intval', $children));
            }

            $query = $folderId === null
                ? $query->whereNull('media_folder_id')
                : $query->whereIn('media_folder_id', $folderIds);
        }

        return $query->orderBy('id')->pluck('id')->map(static fn ($id): int => (int) $id)->all();
    }

    /**
     * @param  list<int>  $assetIds
     * @return list<MediaBulkCandidate>
     */
    public function candidates(int $workspaceId, array $assetIds, bool $trashed): array
    {
        if ($assetIds === []) {
            return [];
        }

        $rows = DB::table(self::ASSETS)
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $assetIds)
            ->when($trashed,
                static fn ($query) => $query->whereNotNull('deleted_at'),
                static fn ($query) => $query->whereNull('deleted_at'),
            )
            ->orderBy('id')
            ->get();

        $ids = $rows->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        // TEK sorgu, dosya başına bir tane değil: yayın bağı olan
        // kimliklerin kümesi bir kerede okunur.
        $published = $ids === [] ? [] : DB::table('media_usages')
            ->where('workspace_id', $workspaceId)
            ->whereIn('media_asset_id', $ids)
            ->whereNotNull('publication_id')
            ->pluck('media_asset_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $publishedSet = array_flip($published);

        return $rows->map(static function (object $row) use ($publishedSet): MediaBulkCandidate {
            $name = (string) ($row->display_name ?? '');

            return new MediaBulkCandidate(
                id: (int) $row->id,
                name: $name === '' ? (string) $row->original_name : $name,
                sizeBytes: (int) $row->size_bytes,
                mimeType: (string) $row->mime_type,
                status: (string) $row->status,
                folderId: $row->media_folder_id === null ? null : (int) $row->media_folder_id,
                trashed: $row->deleted_at !== null,
                legalHold: $row->legal_hold_at !== null,
                usedByPublication: isset($publishedSet[(int) $row->id]),
            );
        })->values()->all();
    }

    /** @param list<int> $assetIds */
    public function purgeAssets(int $workspaceId, array $assetIds): int
    {
        if ($assetIds === []) {
            return 0;
        }

        $rows = DB::table(self::ASSETS)
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $assetIds)
            ->whereNotNull('deleted_at')
            // İki kilit sahibin isteğinden ÜSTÜNDÜR: yayında kullanılan bir
            // görsel çöpte bile silinmez (yayın anlık görüntüsü onu
            // gösteriyor olabilir), yasal saklamadaki hiç silinmez.
            ->whereNull('legal_hold_at')
            ->get();

        $purged = 0;

        foreach ($rows as $row) {
            $assetId = (int) $row->id;

            $usedByPublication = DB::table('media_usages')
                ->where('workspace_id', $workspaceId)
                ->where('media_asset_id', $assetId)
                ->whereNotNull('publication_id')
                ->exists();

            if ($usedByPublication) {
                continue;
            }

            // Dosya gitmediyse satır da gitmez: yetim dosyanın izi
            // kaybolmasın (`EloquentMediaRepository::purgeTrash` ile aynı
            // kural — iki yerde iki farklı davranış olamaz).
            if (Storage::disk(self::QUARANTINE_DISK)->delete((string) $row->disk_path) === false) {
                continue;
            }

            $storageKeys = DB::table('media_blobs')
                ->join('media_renditions', 'media_renditions.media_blob_id', '=', 'media_blobs.id')
                ->join('media_versions', 'media_versions.id', '=', 'media_renditions.media_version_id')
                ->where('media_versions.media_asset_id', $assetId)
                ->pluck('media_blobs.storage_key');

            foreach ($storageKeys as $storageKey) {
                Storage::disk(self::QUARANTINE_DISK)->delete((string) $storageKey);
            }

            DB::table(self::ASSETS)
                ->where('id', $assetId)
                ->update(['lifecycle_status' => LifecycleStatus::Purged->value]);

            DB::table(self::ASSETS)->where('id', $assetId)->delete();
            $purged++;
        }

        return $purged;
    }

    /** @param array{planned:int, applied:int, skipped:int, failed:int} $counts */
    public function recordOperation(
        int $workspaceId,
        string $operationKey,
        string $action,
        string $scope,
        array $counts,
        ?int $actorUserId,
    ): ?int {
        try {
            return (int) DB::table(self::OPERATIONS)->insertGetId([
                'workspace_id' => $workspaceId,
                'operation_key' => $operationKey,
                'action' => $action,
                'scope' => $scope,
                'planned_count' => $counts['planned'],
                'applied_count' => $counts['applied'],
                'skipped_count' => $counts['skipped'],
                'failed_count' => $counts['failed'],
                'actor_user_id' => $actorUserId,
                'created_at' => now(),
            ]);
        } catch (QueryException) {
            /*
                Tekil kısıt patladı: aynı anahtarla ikinci bir iş. Bu bir
                HATA DEĞİL, kısıtın işini yapmasıdır — çağıran bunu
                "tekrar oynatıldı" diye okur ve sahibe iş iki kez çalışmış
                gibi görünmez.
            */
            return null;
        }
    }

    public function operationExists(int $workspaceId, string $operationKey): bool
    {
        return DB::table(self::OPERATIONS)
            ->where('workspace_id', $workspaceId)
            ->where('operation_key', $operationKey)
            ->exists();
    }

    /**
     * @return list<array{operationKey:string, action:string, scope:string, applied:int, skipped:int, failed:int, actor:?string, at:?string}>
     */
    public function recentOperations(int $workspaceId, int $limit = 25): array
    {
        return DB::table(self::OPERATIONS.' as o')
            ->leftJoin('users as u', 'u.id', '=', 'o.actor_user_id')
            ->where('o.workspace_id', $workspaceId)
            ->orderByDesc('o.created_at')
            ->orderByDesc('o.id')
            ->limit($limit)
            ->get(['o.operation_key', 'o.action', 'o.scope', 'o.applied_count', 'o.skipped_count', 'o.failed_count', 'o.created_at', 'u.email'])
            ->map(static fn (object $row): array => [
                'operationKey' => (string) $row->operation_key,
                'action' => (string) $row->action,
                'scope' => (string) $row->scope,
                'applied' => (int) $row->applied_count,
                'skipped' => (int) $row->skipped_count,
                'failed' => (int) $row->failed_count,
                // Aktörün ADI değil E-POSTASI: bir ekipte iki "Mehmet"
                // olabilir ve "Mehmet sildi" hiçbir soruyu kapatmaz.
                'actor' => $row->email === null ? null : (string) $row->email,
                'at' => $row->created_at === null ? null : (string) $row->created_at,
            ])->values()->all();
    }
}
