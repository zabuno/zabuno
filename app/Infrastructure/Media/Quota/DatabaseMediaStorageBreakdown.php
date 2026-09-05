<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Quota;

use App\Application\Media\Dto\MediaStorageBreakdown;
use App\Application\Media\Port\MediaStorageBreakdownPort;
use App\Domain\Media\LifecycleStatus;
use Illuminate\Support\Facades\DB;

/**
 * Kırılım `media_assets`'tan SAYILIR — ayrı bir sayaç tutulmaz.
 * `ConfigMediaQuota` ile aynı gerekçe: sayaç kayar, tablo kaymaz.
 *
 * İki sorgu, iki ayrı soru:
 *
 *   1. Kullanılabilir dosyalar slot başına gruplanır (indeks:
 *      `workspace_id, slot`). Kategoriye çevirme veri tabanında DEĞİL,
 *      alan katmanında yapılır (`StorageCategory`) — eşleme bir ürün
 *      kararıdır ve SQL'e gömülürse gerekçesiyle birlikte kaybolur.
 *   2. Çöp ayrı sayılır (indeks: `workspace_id, lifecycle_status`).
 *
 * `purged` HİÇBİR yerde sayılmaz: satır denetim için durur, dosyası
 * gitmiştir. Onu saymak sahibi artık var olmayan bir baytı silmeye
 * çalıştırırdı.
 */
final class DatabaseMediaStorageBreakdown implements MediaStorageBreakdownPort
{
    public function breakdownFor(int $workspaceId): MediaStorageBreakdown
    {
        $usable = DB::table('media_assets')
            ->where('workspace_id', $workspaceId)
            /*
                Boş `lifecycle_status` KULLANILABİLİR sayılır. Sütun sonradan
                eklendi; eski bir satırda boş kalırsa onu sessizce yok saymak
                sahibin dosyasını kırılımdan silerdi — `ConfigMediaQuota` da
                aynı yönde davranır.
            */
            ->where(function ($query): void {
                $query->whereNull('lifecycle_status')
                    ->orWhereNotIn('lifecycle_status', [
                        LifecycleStatus::Trashed->value,
                        LifecycleStatus::Purged->value,
                    ]);
            })
            ->groupBy('slot')
            ->get([
                'slot',
                DB::raw('SUM(size_bytes) as bytes'),
                DB::raw('COUNT(*) as assets'),
            ])
            ->map(static fn (object $row): array => [
                'slot' => (string) $row->slot,
                'bytes' => (int) $row->bytes,
                'assets' => (int) $row->assets,
            ]);

        $trash = DB::table('media_assets')
            ->where('workspace_id', $workspaceId)
            ->where('lifecycle_status', LifecycleStatus::Trashed->value);

        return MediaStorageBreakdown::fromRows(
            $usable,
            (int) (clone $trash)->sum('size_bytes'),
            (int) (clone $trash)->count(),
        );
    }
}
