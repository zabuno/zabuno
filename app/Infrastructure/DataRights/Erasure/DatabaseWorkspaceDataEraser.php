<?php

declare(strict_types=1);

namespace App\Infrastructure\DataRights\Erasure;

use App\Application\DataRights\Port\WorkspaceDataEraserPort;
use App\Domain\DataRights\TenantDataScope;
use App\Domain\Tenancy\WorkspaceState;
use App\Infrastructure\DataRights\Export\TenantRowReader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Silme gerçekten siler ve SAYAR — FF-226 (`docs/138` §4, §5).
 *
 * ÜÇ KURAL:
 *
 * 1. **Sıra terstir.** Kapsam listesi ebeveynden çocuğa yazılıdır; silme
 *    çocuktan ebeveyne yürür. Ters gidilseydi yabancı anahtar kısıtı ilk
 *    tabloda düşer ve işlem hiç başlamazdı.
 *
 * 2. **Tek işlem.** Yarısı silinmiş bir çalışma alanı, silinmemiş bir
 *    çalışma alanından kötüdür: menüsü yok ama karekodu var, misafir boş
 *    bir sayfaya bakıyor ve kimse neyin kaldığını bilmiyor. Ya hepsi ya
 *    hiçbiri.
 *
 * 3. **Sayı döner.** "Her şey silindi" cümlesi ancak sayılabildiği kadar
 *    doğrudur. Silinen satır sayıları deftere yazılır ve ekranda görünür.
 *
 * DOSYALAR DA GİDER. Satırı silip diskteki fotoğrafı bırakmak, "sildik"
 * demenin en sinsi hâli olurdu: veritabanı temiz görünür, veri durur.
 *
 * ÇALIŞMA ALANININ KENDİ SATIRI KALIR — mezar taşı olarak, `state`
 * `deleted`. Silinseydi kesilmiş faturanın `workspace_id` alanı sahipsiz
 * kalır ve silme talebinin kaydı hangi çalışma alanına ait olduğunu
 * söyleyemezdi.
 */
final class DatabaseWorkspaceDataEraser implements WorkspaceDataEraserPort
{
    public function assetsUnderLegalHold(int $workspaceId): int
    {
        return DB::table('media_assets')
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('legal_hold_at')
            ->count();
    }

    public function erase(int $workspaceId): array
    {
        $reader = new TenantRowReader($workspaceId);

        // KİMLİKLER ÖNCE TOPLANIR: silme başladıktan sonra çocuk tablonun
        // ebeveyni artık orada olmayacak ve bağ takip edilemeyecek.
        $plan = [];

        foreach (TenantDataScope::tables() as $table) {
            $plan[$table->name] = $reader->idsFor($table);
        }

        $files = $this->fileTargets($workspaceId);

        $counts = DB::transaction(function () use ($plan): array {
            $counts = [];

            foreach (TenantDataScope::erasedTablesInDeletionOrder() as $table) {
                $deleted = 0;

                foreach (array_chunk($plan[$table->name], 500) as $chunk) {
                    $deleted += DB::table($table->name)->whereIn('id', $chunk)->delete();
                }

                $counts[$table->name] = $deleted;
            }

            return $counts;
        });

        $counts['storage_files'] = $this->deleteFiles($files);

        /*
            MEZAR TAŞI, YENİ BİR HÂL DEĞİL. `WorkspaceState::Deleted` bu
            depoda zaten vardı ve "bağlam olarak seçilemez" kuralını zaten
            taşıyor; silme için yeni bir hâl uydurmak, aynı anlamı iki adla
            söylemek olurdu.
        */
        DB::table('workspaces')->where('id', $workspaceId)->update([
            'state' => WorkspaceState::Deleted->value,
            'updated_at' => Carbon::now(),
        ]);

        return $counts;
    }

    /**
     * Silinecek dosyaların yolları, SİLMEDEN ÖNCE okunur.
     *
     * @return array<string, list<string>> disk adı → yollar
     */
    private function fileTargets(int $workspaceId): array
    {
        $targets = [];

        $assets = DB::table('media_assets')->where('workspace_id', $workspaceId)->pluck('disk_path');

        foreach ($assets as $path) {
            if (is_string($path) && $path !== '') {
                $targets['local'][] = $path;
            }
        }

        $blobs = DB::table('media_blobs')->where('workspace_id', $workspaceId)->get(['disk', 'storage_key']);

        foreach ($blobs as $blob) {
            $disk = is_string($blob->disk) && $blob->disk !== '' ? $blob->disk : 'local';
            $key = (string) $blob->storage_key;

            if ($key !== '') {
                $targets[$disk][] = $key;
            }
        }

        $exports = DB::table('workspace_data_requests')
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('artifact_path')
            ->pluck('artifact_path');

        $exportDisk = config('data-rights.export.disk');
        $exportDisk = is_string($exportDisk) && $exportDisk !== '' ? $exportDisk : 'local';

        foreach ($exports as $path) {
            if (is_string($path) && $path !== '') {
                $targets[$exportDisk][] = $path;
            }
        }

        return $targets;
    }

    /**
     * @param  array<string, list<string>>  $targets
     */
    private function deleteFiles(array $targets): int
    {
        $deleted = 0;

        foreach ($targets as $disk => $paths) {
            $storage = Storage::disk($disk);

            foreach (array_unique($paths) as $path) {
                /*
                    OLMAYAN DOSYA SAYILMAZ. `delete()` var olmayan bir yol
                    için de `true` dönebilir; sayının anlamı "gerçekten
                    kaldırılan dosya" olmalı, yoksa rakam kendi kendini
                    doğrulayan bir süs olurdu.
                */
                if ($storage->exists($path) && $storage->delete($path)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }
}
