<?php

declare(strict_types=1);

namespace App\Application\Ai\Batch;

use Illuminate\Support\Facades\DB;

/**
 * Bütün sayfalar terminal hâle gelince toplayıcıyı çalıştırır ve özeti
 * kalıcı hafızaya yazar. Hiçbir sayfa okunamadıysa parti `failed`,
 * en az biri okunduysa `collected` — kısmi başarı boşa gitmez.
 */
final class FinishMenuBatch
{
    public function __construct(private readonly MenuBatchCollector $collector) {}

    public function handle(int $batchId): void
    {
        $pages = DB::table('ai_batch_pages')->where('ai_batch_id', $batchId)->orderBy('position')->get();

        if ($pages->contains(fn (object $page): bool => in_array((string) $page->state, ['queued', 'running'], true))) {
            return;
        }

        $artifacts = [];
        $failed = [];

        foreach ($pages as $page) {
            if ((string) $page->state !== 'done' || $page->ai_artifact_id === null) {
                $failed[] = ['mediaAssetId' => (int) $page->media_asset_id, 'reason' => (string) ($page->failure_reason ?? 'unknown')];

                continue;
            }

            $row = DB::table('ai_artifacts')->where('id', (int) $page->ai_artifact_id)->first();

            if ($row === null) {
                $failed[] = ['mediaAssetId' => (int) $page->media_asset_id, 'reason' => 'artifact-missing'];

                continue;
            }

            $fields = json_decode((string) $row->fields, true) ?: [];
            $artifacts[] = [
                'artifactId' => (int) $row->id,
                'page' => (int) $page->position,
                'fields' => array_map(static fn (array $field): array => [
                    'name' => (string) ($field['name'] ?? ''),
                    'value' => (array) ($field['value'] ?? []),
                    'confidence' => (float) ($field['confidence'] ?? 0.0),
                    'uncertain' => (bool) ($field['uncertain'] ?? false),
                ], $fields),
            ];
        }

        $summary = $this->collector->collect($artifacts, $failed);

        DB::table('ai_batches')->where('id', $batchId)->update([
            'state' => $artifacts === [] ? 'failed' : 'collected',
            'done_pages' => count($artifacts),
            'failed_pages' => count($failed),
            'collector_summary' => json_encode($summary, JSON_UNESCAPED_UNICODE),
            'finished_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
