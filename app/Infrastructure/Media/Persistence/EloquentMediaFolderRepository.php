<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Persistence;

use App\Application\Media\Dto\MediaFolderSummary;
use App\Application\Media\Port\MediaFolderRepositoryPort;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentMediaFolderRepository implements MediaFolderRepositoryPort
{
    public function listForWorkspace(int $workspaceId): array
    {
        $folders = MediaFolderRecord::query()
            ->where('workspace_id', $workspaceId)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        /*
            Sayaç tek bir toplu sorguyla alınır. Klasör başına ayrı sayım,
            sekiz klasörlü bir kenar çubuğunu her açılışta dokuz sorguya
            çevirirdi ve klasör sayısı arttıkça sessizce yavaşlardı.
            `whereNull('deleted_at')`: çöpe atılan fotoğraf kütüphanede
            görünmüyor, sayaçta da görünmemeli — yoksa sahip "4" yazan
            klasöre girip 3 dosya bulurdu.
        */
        $counts = DB::table('media_assets')
            ->where('workspace_id', $workspaceId)
            ->whereNull('deleted_at')
            ->whereNotNull('media_folder_id')
            ->groupBy('media_folder_id')
            ->pluck(DB::raw('count(*)'), 'media_folder_id');

        return $this->inSidebarOrder($folders, $counts);
    }

    public function find(int $workspaceId, int $folderId): ?MediaFolderSummary
    {
        $folder = MediaFolderRecord::query()
            ->where('workspace_id', $workspaceId)
            ->find($folderId);

        return $folder === null ? null : $this->toSummary($folder, 0);
    }

    public function nameTaken(int $workspaceId, string $name, ?int $parentId, ?int $exceptFolderId = null): bool
    {
        return MediaFolderRecord::query()
            ->where('workspace_id', $workspaceId)
            ->when($parentId === null,
                fn ($query) => $query->whereNull('parent_id'),
                fn ($query) => $query->where('parent_id', $parentId),
            )
            ->where('name', $name)
            ->when($exceptFolderId !== null, fn ($query) => $query->whereKeyNot($exceptFolderId))
            ->exists();
    }

    public function create(int $workspaceId, string $name, ?int $parentId): MediaFolderSummary
    {
        /*
            Sıra, aynı seviyenin sonuna eklenir: yeni klasör sahibin
            kurduğu düzenin ortasına düşmez. `position` kendi kardeşleri
            içinde sayılır — kök klasörler ve bir klasörün çocukları ayrı
            listelerdir.
        */
        $position = (int) MediaFolderRecord::query()
            ->where('workspace_id', $workspaceId)
            ->when($parentId === null,
                fn ($query) => $query->whereNull('parent_id'),
                fn ($query) => $query->where('parent_id', $parentId),
            )
            ->max('position');

        $folder = MediaFolderRecord::query()->create([
            'workspace_id' => $workspaceId,
            'parent_id' => $parentId,
            'name' => $name,
            'position' => $position + 1,
        ]);

        return $this->toSummary($folder, 0);
    }

    public function rename(int $workspaceId, int $folderId, string $name): bool
    {
        return MediaFolderRecord::query()
            ->where('workspace_id', $workspaceId)
            ->whereKey($folderId)
            ->update(['name' => $name, 'updated_at' => now()]) === 1;
    }

    public function hasChildren(int $workspaceId, int $folderId): bool
    {
        return MediaFolderRecord::query()
            ->where('workspace_id', $workspaceId)
            ->where('parent_id', $folderId)
            ->exists();
    }

    public function deleteAndReleaseFiles(int $workspaceId, int $folderId): int
    {
        return DB::transaction(function () use ($workspaceId, $folderId): int {
            /*
                Dosyalar ÖNCE serbest bırakılır, klasör SONRA silinir.
                Veritabanı `nullOnDelete` ile aynı sonucu verirdi ama kaç
                dosyanın etkilendiğini sahibe söyleyemezdik — "Kampanyalar
                silindi, 12 dosya Tümü'ne taşındı" cümlesi burada doğuyor.
                Çöptekiler de serbest bırakılır: geri alındığında yok olmuş
                bir klasöre işaret etmesinler.
            */
            $released = DB::table('media_assets')
                ->where('workspace_id', $workspaceId)
                ->where('media_folder_id', $folderId)
                ->update(['media_folder_id' => null, 'updated_at' => now()]);

            MediaFolderRecord::query()
                ->where('workspace_id', $workspaceId)
                ->whereKey($folderId)
                ->delete();

            return $released;
        });
    }

    public function moveAsset(int $workspaceId, int $assetId, ?int $folderId): bool
    {
        return DB::table('media_assets')
            ->where('id', $assetId)
            ->where('workspace_id', $workspaceId)
            ->update(['media_folder_id' => $folderId, 'updated_at' => now()]) === 1;
    }

    /**
     * Kök klasör, hemen ardından kendi çocukları. Ekran girintiyi bu
     * sıraya güvenerek çiziyor; sıralamayı istemciye bırakmak, aynı
     * kuralın iki yerde yazılması olurdu.
     *
     * @param  Collection<int, MediaFolderRecord>  $folders
     * @param  Collection<int|string, int>  $counts
     * @return list<MediaFolderSummary>
     */
    private function inSidebarOrder(Collection $folders, Collection $counts): array
    {
        $childrenByParent = $folders
            ->filter(fn (MediaFolderRecord $folder): bool => $folder->parent_id !== null)
            ->groupBy(fn (MediaFolderRecord $folder): int => (int) $folder->parent_id);

        $ordered = [];

        foreach ($folders->filter(fn (MediaFolderRecord $folder): bool => $folder->parent_id === null) as $root) {
            $ordered[] = $this->toSummary($root, (int) ($counts[$root->getKey()] ?? 0));

            foreach ($childrenByParent->get((int) $root->getKey(), collect()) as $child) {
                $ordered[] = $this->toSummary($child, (int) ($counts[$child->getKey()] ?? 0));
            }
        }

        return $ordered;
    }

    private function toSummary(MediaFolderRecord $folder, int $fileCount): MediaFolderSummary
    {
        return new MediaFolderSummary(
            id: (int) $folder->getKey(),
            workspaceId: (int) $folder->workspace_id,
            name: (string) $folder->name,
            parentId: $folder->parent_id === null ? null : (int) $folder->parent_id,
            position: (int) $folder->position,
            fileCount: $fileCount,
        );
    }
}
