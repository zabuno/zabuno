<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Media\Port\MediaQuotaPort;
use App\Application\Media\Port\MediaRepositoryPort;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Çöp → süre → kalıcı silme (`docs/49` Faz 5 madde 3, Faz 7 madde 1).
 *
 *   php artisan media:purge-trash            # her çalışma alanı kendi planının süresiyle
 *   php artisan media:purge-trash --days=7   # hepsine aynı süre (işletim kararı)
 *
 * Yayında kullanılan varlığa dokunmaz. Zamanlayıcıya bağlanması işletim
 * kararıdır (docs/42); komut kendi başına zamanlamaz.
 */
final class PurgeMediaTrashCommand extends Command
{
    protected $signature = 'media:purge-trash {--days= : kaç günden eski çöp (varsayılan: çalışma alanının planı)}';

    protected $description = 'Permanently delete trashed media older than the plan retention (file + rows). Skips anything a publication still shows.';

    public function handle(MediaRepositoryPort $media, MediaQuotaPort $quota): int
    {
        $override = $this->option('days') === null ? null : max(1, (int) $this->option('days'));
        $total = 0;

        $workspaceIds = DB::table('media_assets')
            ->whereNotNull('deleted_at')
            ->distinct()
            ->pluck('workspace_id');

        foreach ($workspaceIds as $workspaceId) {
            $days = $override ?? $quota->trashRetentionDaysFor((int) $workspaceId);
            $purged = $media->purgeTrash($days, (int) $workspaceId);
            $total += $purged;

            if ($purged > 0) {
                $this->line("Çalışma alanı {$workspaceId}: {$purged} silindi (>{$days} gün).");
            }
        }

        $this->info("Kalıcı silinen: {$total}.");

        return self::SUCCESS;
    }
}
