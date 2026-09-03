<?php

declare(strict_types=1);

namespace App\Application\Ai\Batch;

use App\Jobs\ExtractMenuBatchPageJob;
use Illuminate\Support\Facades\DB;

/**
 * Toplu okumayı BAŞLATIR: kalıcı satırlar (`ai_batches`, `ai_batch_pages`),
 * sonra sayfa başına bir kuyruk işi. Sayfalar birbirinden bağımsızdır;
 * biri düşerse ötekiler okunur (`docs/97` R30 kısmi başarısızlık).
 */
final class StartMenuBatch
{
    /**
     * @param  list<int>  $mediaAssetIds  sıralı sayfa listesi (tenant'a ait olduğu ÇAĞIRAN tarafından doğrulanmış)
     */
    public function handle(int $workspaceId, int $menuId, int $userId, array $mediaAssetIds): int
    {
        $batchId = (int) DB::table('ai_batches')->insertGetId([
            'workspace_id' => $workspaceId,
            'menu_id' => $menuId,
            'capability' => 'menu.extract',
            'purpose' => 'batch',
            'state' => 'queued',
            'total_pages' => count($mediaAssetIds),
            'requested_by_user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pageIds = [];

        foreach (array_values($mediaAssetIds) as $position => $mediaAssetId) {
            $pageIds[] = (int) DB::table('ai_batch_pages')->insertGetId([
                'ai_batch_id' => $batchId,
                'workspace_id' => $workspaceId,
                'media_asset_id' => $mediaAssetId,
                'position' => $position + 1,
                'state' => 'queued',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Satırlar YAZILDIKTAN sonra işler kuyruğa girer: iş, satırı
        // bulamazsa hiçbir şey yapamaz.
        foreach ($pageIds as $pageId) {
            ExtractMenuBatchPageJob::dispatch($workspaceId, $batchId, $pageId);
        }

        return $batchId;
    }
}
