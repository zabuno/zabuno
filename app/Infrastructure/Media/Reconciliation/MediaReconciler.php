<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Reconciliation;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Uzlaştırma (`docs/49` Faz 7 madde 5): veritabanında olup diskte olmayan
 * (kırık kayıt) ve diskte olup veritabanında olmayan (yetim dosya).
 *
 * Varsayılan SALT RAPOR. `--fix` yalnız yetim dosyayı siler ve kırık
 * kaydı `failed`'a çeker; hiçbir zaman satır silmez — silmek purge'ün
 * işidir ve o yayın kontrolünden geçer.
 */
final class MediaReconciler
{
    private const DISK = 'local';

    /**
     * @return array{missingFiles:list<array{table:string,id:int,path:string}>, orphanFiles:list<string>, fixed:int}
     */
    public function run(bool $fix = false): array
    {
        $disk = Storage::disk(self::DISK);
        $missing = [];
        $referenced = [];
        $fixed = 0;

        foreach (DB::table('media_assets')->get(['id', 'disk_path', 'status']) as $asset) {
            $referenced[(string) $asset->disk_path] = true;

            if (! $disk->exists((string) $asset->disk_path)) {
                $missing[] = ['table' => 'media_assets', 'id' => (int) $asset->id, 'path' => (string) $asset->disk_path];

                if ($fix && (string) $asset->status !== 'failed') {
                    DB::table('media_assets')->where('id', $asset->id)->update(['status' => 'failed', 'updated_at' => now()]);
                    $fixed++;
                }
            }
        }

        foreach (DB::table('media_blobs')->get(['id', 'storage_key']) as $blob) {
            $referenced[(string) $blob->storage_key] = true;

            if (! $disk->exists((string) $blob->storage_key)) {
                $missing[] = ['table' => 'media_blobs', 'id' => (int) $blob->id, 'path' => (string) $blob->storage_key];
            }
        }

        $orphans = [];

        foreach (['quarantine', 'renditions'] as $root) {
            foreach ($disk->allFiles($root) as $path) {
                if (! isset($referenced[$path])) {
                    $orphans[] = $path;

                    if ($fix) {
                        $disk->delete($path);
                        $fixed++;
                    }
                }
            }
        }

        return ['missingFiles' => $missing, 'orphanFiles' => $orphans, 'fixed' => $fixed];
    }
}
