<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Media\Reconciliation\MediaReconciler;
use Illuminate\Console\Command;

/**
 *   php artisan media:reconcile          # rapor
 *   php artisan media:reconcile --fix    # yetim dosyayı sil, kırık kaydı failed'a çek
 *
 * Restore kabulü (`docs/49` Faz 7): "yedek alındı" değil, "geri yüklendi
 * ve referanslar çalıştı" — bu komut o cümlenin ölçüsüdür: sıfır kırık
 * kayıt, sıfır yetim dosya.
 */
final class ReconcileMediaCommand extends Command
{
    protected $signature = 'media:reconcile {--fix : yetim dosyaları sil, kırık kayıtları failed olarak işaretle}';

    protected $description = 'Compare media rows with files on disk: report missing files and orphan files; --fix cleans orphans and marks broken rows.';

    public function handle(MediaReconciler $reconciler): int
    {
        $report = $reconciler->run((bool) $this->option('fix'));

        foreach ($report['missingFiles'] as $row) {
            $this->line("KIRIK  {$row['table']}#{$row['id']} → {$row['path']}");
        }

        foreach ($report['orphanFiles'] as $path) {
            $this->line("YETİM  {$path}");
        }

        $this->info(sprintf(
            'Kırık kayıt: %d · Yetim dosya: %d · Düzeltilen: %d',
            count($report['missingFiles']),
            count($report['orphanFiles']),
            $report['fixed'],
        ));

        return ($report['missingFiles'] === [] && $report['orphanFiles'] === []) ? self::SUCCESS : self::FAILURE;
    }
}
