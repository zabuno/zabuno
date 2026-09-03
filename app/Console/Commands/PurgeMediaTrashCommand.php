<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Media\Port\MediaRepositoryPort;
use Illuminate\Console\Command;

/**
 * Çöp → süre → kalıcı silme (`docs/49` Faz 5 madde 3).
 *
 *   php artisan media:purge-trash            # config'deki süre (30 gün)
 *   php artisan media:purge-trash --days=7
 *
 * Yayında kullanılan varlığa dokunmaz. Zamanlayıcıya bağlanması işletim
 * kararıdır (docs/42); komut kendi başına zamanlamaz.
 */
final class PurgeMediaTrashCommand extends Command
{
    protected $signature = 'media:purge-trash {--days= : kaç günden eski çöp (varsayılan config)}';

    protected $description = 'Permanently delete trashed media older than the retention period (file + rows). Skips anything a publication still shows.';

    public function handle(MediaRepositoryPort $media): int
    {
        $days = $this->option('days') === null
            ? (int) config('media-slots.limits.trash_retention_days', 30)
            : (int) $this->option('days');

        $purged = $media->purgeTrash(max(1, $days));

        $this->info("Kalıcı silinen: {$purged} (>{$days} gün çöpte).");

        return self::SUCCESS;
    }
}
