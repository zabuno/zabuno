<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\DataRights\UseCase\RunDueErasures;
use Illuminate\Console\Command;

/**
 * Penceresi dolan silmeleri yürütür — FF-226 (`docs/138` §4).
 *
 * KOMUT BİR SAYI YAZMAZ. Pencerenin uzunluğu `config/data-rights.php`
 * içindedir ve komut yalnız "vakti gelmiş olan" satırları sorar. Buraya
 * bir `--days` verilseydi, süre iki yerde yaşar ve iki gün sonra
 * ayrışırlardı (`media:purge-trash` ile aynı ders).
 */
final class RunDueWorkspaceErasuresCommand extends Command
{
    protected $signature = 'zabuno:run-due-erasures';

    protected $description = 'Penceresi dolan veri silme taleplerini yürütür ve süresi dolan dışa aktarma arşivlerini diskten kaldırır.';

    public function handle(RunDueErasures $run): int
    {
        $result = $run->handle();

        $this->info(sprintf(
            'Silinen çalışma alanı: %d · düşen: %d · süresi dolan arşiv: %d',
            $result['erased'],
            $result['failed'],
            $result['expired'],
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
