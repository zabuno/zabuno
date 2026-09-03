<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Media\UseCase\ReprocessMediaAsset;
use App\Models\MediaAsset;
use Illuminate\Console\Command;

/**
 * Toplu yeniden üretim — `docs/49` Faz 3 madde 5.
 *
 *   php artisan media:reprocess --workspace=7          # bir restoranın hazır görselleri
 *   php artisan media:reprocess --workspace=7 --asset=42
 *
 * Boru hattı ya da slot politikası değiştiğinde; asıl dosyalar değişmez,
 * her varlık yeni bir sürüm alır. Başarısız olan `ready` kalır ve sebebi
 * iş kaydına yazılır.
 */
final class ReprocessMediaCommand extends Command
{
    protected $signature = 'media:reprocess {--workspace= : workspace kimliği (zorunlu)} {--asset= : tek bir varlık}';

    protected $description = 'Regenerate the rendition set of ready media assets from their untouched originals, as new versions.';

    public function handle(ReprocessMediaAsset $reprocess): int
    {
        $workspaceId = (int) $this->option('workspace');

        if ($workspaceId < 1) {
            $this->error('--workspace zorunlu.');

            return self::FAILURE;
        }

        $query = MediaAsset::query()->where('workspace_id', $workspaceId)->where('status', 'ready');

        if ($this->option('asset') !== null) {
            $query->where('id', (int) $this->option('asset'));
        }

        $tally = ['reprocessed' => 0, 'failed' => 0, 'not-ready' => 0];

        foreach ($query->orderBy('id')->pluck('id') as $assetId) {
            $outcome = $reprocess($workspaceId, (int) $assetId);
            $tally[$outcome]++;
            $this->line("#{$assetId}: {$outcome}");
        }

        $this->info(sprintf('Yeniden üretildi: %d, başarısız: %d.', $tally['reprocessed'], $tally['failed']));

        return self::SUCCESS;
    }
}
