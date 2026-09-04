<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Persistence;

use App\Application\Media\Port\MediaConversionPort;
use App\Domain\Media\MediaAssetStatus;
use Illuminate\Support\Facades\DB;

/**
 * DÖNÜŞTÜR bölümünün okuma sorguları (`docs/108` §6.3).
 *
 * Buradaki iki sorgu da UYDURMAZ: biri seçilebilir dosyaları sayar, diğeri
 * daha önce üretilmiş türevleri TARTAR.
 */
final class EloquentMediaConversion implements MediaConversionPort
{
    /**
     * MIME'den kaynağın konuştuğu biçim adına.
     *
     * Ekranda "image/jpeg" değil "JPEG" yazar; çeviri MIME ile değil biçim
     * adıyla anahtarlanır ve `image/jpg` ile `image/jpeg` aynı şeydir.
     */
    private const FORMAT_BY_MIME = [
        'image/jpeg' => 'jpeg',
        'image/jpg' => 'jpeg',
        'image/pjpeg' => 'jpeg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'image/gif' => 'gif',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
        'image/tiff' => 'tiff',
        'image/bmp' => 'bmp',
    ];

    /** @return list<array{id:int, name:string, sizeBytes:int, format:string}> */
    public function convertibleAssets(int $workspaceId, int $limit): array
    {
        $rows = DB::table('media_assets')
            ->where('workspace_id', $workspaceId)
            ->whereNull('deleted_at')
            ->where('status', MediaAssetStatus::Ready->value)
            ->where('mime_type', 'like', 'image/%')
            /*
                SVG DIŞARIDA — kaynak da onu dışarıda tutuyor
                (`f.ext !== 'SVG'`). Vektör her ölçekte keskindir; onu
                AVIF'e çevirmek bir kazanç değil, geri alınamayan bir
                kayıptır (`SvgMediaAssetProcessor`in gerekçesiyle aynı).
            */
            ->where('mime_type', '!=', 'image/svg+xml')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'original_name', 'size_bytes', 'mime_type']);

        return array_values(array_map(static function (object $row): array {
            $mime = strtolower((string) $row->mime_type);

            return [
                'id' => (int) $row->id,
                // Adsız bir dosya listede seçilemez hâle gelirdi; boşsa
                // hiç değilse biçim adı okunur bir şey söyler.
                'name' => (string) ($row->original_name ?? ''),
                'sizeBytes' => (int) $row->size_bytes,
                'format' => self::FORMAT_BY_MIME[$mime] ?? 'other',
            ];
        }, $rows->all()));
    }

    /** @return array<string, array{assets:int, originalBytes:int, convertedBytes:int}> */
    public function measuredByFormat(int $workspaceId): array
    {
        /*
            YALNIZ EN SON SÜRÜM sayılır. Eski sürümlerin türevleri diskte
            durur (hiçbir satır silinmez) ama misafire giden onlar değildir;
            geçersiz kalmış bir sürümü "bugünkü kazancım" diye göstermek
            sahibi yanıltırdı.
        */
        $latestVersions = DB::table('media_versions')
            ->selectRaw('media_asset_id, max(id) as version_id')
            ->groupBy('media_asset_id');

        $rows = DB::table('media_assets as a')
            ->joinSub($latestVersions, 'lv', 'lv.media_asset_id', '=', 'a.id')
            ->join('media_renditions as r', 'r.media_version_id', '=', 'lv.version_id')
            ->join('media_blobs as b', 'b.id', '=', 'r.media_blob_id')
            ->where('a.workspace_id', $workspaceId)
            ->whereNull('a.deleted_at')
            ->where('a.status', MediaAssetStatus::Ready->value)
            // Varlık + biçim başına TEK satır: o biçimin en büyük türevi.
            // Bütün türevleri toplamak yanlış bir karşılaştırma olurdu —
            // tarayıcı bir sayfada tek bir türev indirir.
            ->groupBy('r.format', 'a.id', 'a.size_bytes')
            ->selectRaw('r.format as format, a.size_bytes as original_bytes, max(b.size_bytes) as largest_bytes')
            ->get();

        $measured = [];

        foreach ($rows as $row) {
            $format = (string) $row->format;

            $measured[$format] ??= ['assets' => 0, 'originalBytes' => 0, 'convertedBytes' => 0];
            $measured[$format]['assets']++;
            $measured[$format]['originalBytes'] += (int) $row->original_bytes;
            $measured[$format]['convertedBytes'] += (int) $row->largest_bytes;
        }

        return $measured;
    }
}
