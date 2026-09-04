<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Persistence;

use App\Application\Media\Port\MediaRegenerationPort;
use App\Domain\Media\MediaAssetStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Boyut motorunun sayıları — HEPSİ gerçek satırlardan.
 *
 * Bu sınıfta tek bir sabit oran, tek bir "yaklaşık %" yoktur. Sahip
 * "yeniden üretimi başlat" düğmesine basmadan önce kaç dosyanın
 * dokunulacağını görür; gördüğü sayı, dokunulacak dosyaların sayısıdır.
 */
final class EloquentMediaRegeneration implements MediaRegenerationPort
{
    /** @return array{affectedAssets:int, existingRenditions:int} */
    public function stats(int $workspaceId): array
    {
        return [
            'affectedAssets' => $this->readyAssets($workspaceId)->count(),
            'existingRenditions' => $this->renditionsQuery($workspaceId)->count(),
        ];
    }

    /** @return array{assets:int, originalBytes:int, largestRenditionBytes:int} */
    public function measuredBytes(int $workspaceId): array
    {
        /*
            Varlık başına TEK satır: asıl boyut ve o varlıktan üretilmiş EN
            BÜYÜK türevin boyutu. Bütün türevleri toplamak yanlış bir
            karşılaştırma olurdu — misafirin tarayıcısı bir sayfada tek bir
            türev indirir, altısını birden değil.
        */
        $rows = DB::table('media_assets as a')
            ->join('media_versions as v', 'v.media_asset_id', '=', 'a.id')
            ->join('media_renditions as r', 'r.media_version_id', '=', 'v.id')
            ->join('media_blobs as b', 'b.id', '=', 'r.media_blob_id')
            ->where('a.workspace_id', $workspaceId)
            ->whereNull('a.deleted_at')
            ->where('a.status', MediaAssetStatus::Ready->value)
            ->groupBy('a.id', 'a.size_bytes')
            ->selectRaw('a.id as asset_id, a.size_bytes as original_bytes, max(b.size_bytes) as largest_bytes')
            ->get();

        $originalBytes = 0;
        $largestRenditionBytes = 0;

        foreach ($rows as $row) {
            $originalBytes += (int) $row->original_bytes;
            $largestRenditionBytes += (int) $row->largest_bytes;
        }

        return [
            'assets' => $rows->count(),
            'originalBytes' => $originalBytes,
            'largestRenditionBytes' => $largestRenditionBytes,
        ];
    }

    /** @return list<int> */
    public function readyAssetIds(int $workspaceId, int $limit): array
    {
        /*
            EN ESKİ ÜRETİLEN ÖNCE. Kimlik sırasıyla dizmek, sınıra takılan
            sahip düğmeye yeniden bastığında aynı ilk yirmi beş dosyayı
            tekrar işlerdi ve kalanına hiç sıra gelmezdi. Yeniden üretilen
            dosya yeni bir sürüm kimliği aldığı için listenin sonuna düşer.

            Hiç sürümü olmayan varlık (`null`) EN ÖNDE gelir: onun türevi
            hiç üretilmemiş demektir ve en çok onun ihtiyacı vardır.
        */
        $ids = DB::table('media_assets as a')
            ->leftJoin('media_versions as v', 'v.media_asset_id', '=', 'a.id')
            ->where('a.workspace_id', $workspaceId)
            ->whereNull('a.deleted_at')
            ->where('a.status', MediaAssetStatus::Ready->value)
            ->groupBy('a.id')
            ->orderByRaw('max(v.id) is null desc')
            ->orderByRaw('max(v.id) asc')
            ->limit($limit)
            ->pluck('a.id');

        return array_values(array_map('intval', $ids->all()));
    }

    /** Yeniden üretimin dokunacağı varlıklar: bu kiracının, çöpte olmayan, hazır. */
    private function readyAssets(int $workspaceId): Builder
    {
        return DB::table('media_assets')
            ->where('workspace_id', $workspaceId)
            ->whereNull('deleted_at')
            ->where('status', MediaAssetStatus::Ready->value);
    }

    private function renditionsQuery(int $workspaceId): Builder
    {
        return DB::table('media_renditions as r')
            ->join('media_versions as v', 'v.id', '=', 'r.media_version_id')
            ->join('media_assets as a', 'a.id', '=', 'v.media_asset_id')
            ->where('a.workspace_id', $workspaceId)
            ->whereNull('a.deleted_at');
    }
}
