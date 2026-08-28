<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Persistence;

use App\Application\Media\Port\MenuMediaPort;
use App\Domain\Media\MediaAssetStatus;
use App\Support\Media\RenditionUrl;
use Illuminate\Support\Facades\DB;

final class EloquentMenuMedia implements MenuMediaPort
{
    public function bindMenuItemImage(int $workspaceId, int $menuItemId, ?int $mediaAssetId): bool
    {
        return $this->bind($workspaceId, 'menu_item', $menuItemId, 'itemImage', $mediaAssetId);
    }

    public function bindBrandLogo(int $workspaceId, int $brandId, ?int $mediaAssetId): bool
    {
        return $this->bind($workspaceId, 'brand', $brandId, 'logo', $mediaAssetId);
    }

    private function bind(int $workspaceId, string $entityType, int $entityId, string $slot, ?int $mediaAssetId): bool
    {
        // TASLAK bağ, `publication_id` null olan satırdır. Yayına yazılmış
        // satırlar burada asla silinmez: onlar geçmişin kaydıdır.
        $draft = DB::table('media_usages')
            ->where('workspace_id', $workspaceId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('slot', $slot)
            ->whereNull('publication_id');

        if ($mediaAssetId === null) {
            $draft->delete();

            return true;
        }

        $asset = DB::table('media_assets')
            ->where('id', $mediaAssetId)
            ->where('workspace_id', $workspaceId)
            ->whereNull('deleted_at')
            ->first();

        if ($asset === null || (string) $asset->status !== MediaAssetStatus::Ready->value) {
            // Hazır olmayan bir görseli bağlamak, menüye kırık bir kutu
            // koymaktır. Sahip önce işlemenin bitmesini beklemeli.
            return false;
        }

        $versionId = DB::table('media_versions')
            ->where('media_asset_id', $mediaAssetId)
            ->orderByDesc('version_number')
            ->value('id');

        if ($versionId === null) {
            return false;
        }

        $draft->delete();

        DB::table('media_usages')->insert([
            'workspace_id' => $workspaceId,
            'media_asset_id' => $mediaAssetId,
            'media_version_id' => (int) $versionId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'slot' => $slot,
            'alt_text_override' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    public function snapshotImage(int $workspaceId, string $entityType, int $entityId): ?array
    {
        $usage = DB::table('media_usages')
            ->where('workspace_id', $workspaceId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->whereNull('publication_id')
            ->orderByDesc('id')
            ->first();

        if ($usage === null || $usage->media_version_id === null) {
            return null;
        }

        return $this->imageForVersion($workspaceId, (int) $usage->media_version_id, (int) $usage->media_asset_id, $usage->alt_text_override);
    }

    /** @return array{versionId:int,altText:string,width:int,height:int,sources:list<array{width:int,url:string}>}|null */
    private function imageForVersion(int $workspaceId, int $versionId, int $assetId, ?string $altOverride): ?array
    {
        $renditions = DB::table('media_renditions')
            ->join('media_blobs', 'media_blobs.id', '=', 'media_renditions.media_blob_id')
            ->where('media_renditions.media_version_id', $versionId)
            ->where('media_blobs.workspace_id', $workspaceId)
            ->orderBy('media_renditions.width')
            ->select([
                'media_renditions.id as id',
                'media_renditions.width as width',
                'media_renditions.height as height',
                'media_renditions.format as format',
                'media_blobs.checksum_sha256 as checksum',
            ])
            ->get();

        if ($renditions->isEmpty()) {
            return null;
        }

        $sources = $renditions->map(fn (object $row): array => [
            'width' => (int) $row->width,
            'url' => RenditionUrl::for((int) $row->id, (string) $row->checksum, (string) $row->format),
        ])->values()->all();

        $largest = $renditions->last();

        // Alternatif metin KULLANIMA göre değişebilir; yoksa varlığın kendi
        // metnine düşülür. İkisi de yoksa alan boş kalır — ve boş `alt`,
        // "bu görsel dekoratiftir" demektir, ki bir yemek fotoğrafı için
        // yanlıştır. Bu yüzden yükleme sırasında alt metin zorunludur.
        $altText = trim((string) ($altOverride ?? ''));

        if ($altText === '') {
            $altText = trim((string) DB::table('media_assets')->where('id', $assetId)->value('alt_text'));
        }

        return [
            'versionId' => $versionId,
            'altText' => $altText,
            'width' => (int) $largest->width,
            'height' => (int) $largest->height,
            'sources' => $sources,
        ];
    }

    public function recordPublicationUsages(int $workspaceId, int $publicationId, array $menuItemIds, ?int $brandId): void
    {
        $targets = array_map(
            static fn (int $id): array => ['menu_item', $id],
            array_values(array_unique($menuItemIds)),
        );

        if ($brandId !== null) {
            $targets[] = ['brand', $brandId];
        }

        foreach ($targets as [$entityType, $entityId]) {
            $draft = DB::table('media_usages')
                ->where('workspace_id', $workspaceId)
                ->where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->whereNull('publication_id')
                ->first();

            if ($draft === null) {
                continue;
            }

            DB::table('media_usages')->insert([
                'workspace_id' => $workspaceId,
                'media_asset_id' => (int) $draft->media_asset_id,
                'media_version_id' => $draft->media_version_id === null ? null : (int) $draft->media_version_id,
                'entity_type' => (string) $draft->entity_type,
                'entity_id' => (int) $draft->entity_id,
                'slot' => (string) $draft->slot,
                // Yayına AYRI bir satır yazılır; taslak satır olduğu yerde
                // kalır. Taslağı işaretlemek, "şu an bağlı olan" ile "şu
                // yayında kullanılmış olan" sorularını tek satıra
                // doldurmak olurdu.
                'publication_id' => $publicationId,
                'alt_text_override' => $draft->alt_text_override,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
