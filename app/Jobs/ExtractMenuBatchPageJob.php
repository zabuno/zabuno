<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Ai\Batch\FinishMenuBatch;
use App\Application\Ai\Exception\ProviderCallException;
use App\Application\Ai\UseCase\ExtractMenuFromImage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Bir sayfa = bir iş (`docs/98` FF-75). Geçici hafıza kuyruktur; kalıcı
 * kayıt `ai_batch_pages`. Dakikalık bütçe `ai-batch` sınırlayıcısıyla
 * kiracı başına uygulanır: 40 sayfa aynı dakikada sağlayıcıya yığılmaz,
 * sıraya girer — "limit şişmesin".
 *
 * Sayfa terminal olunca (done|failed) parti kapanış kontrolü yapılır;
 * son sayfa kimse, toplayıcıyı o çalıştırır.
 */
final class ExtractMenuBatchPageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $workspaceId,
        public readonly int $batchId,
        public readonly int $pageId,
    ) {}

    /** @return list<object> */
    public function middleware(): array
    {
        // Sınır aşılınca iş SİLİNMEZ, kuyruğa geri bırakılır (release):
        // sayfa kaybolmaz, sonraki dakikada okunur.
        return [new RateLimited('ai-batch')];
    }

    public function handle(ExtractMenuFromImage $extract, FinishMenuBatch $finish): void
    {
        $page = DB::table('ai_batch_pages')->where('id', $this->pageId)->where('workspace_id', $this->workspaceId)->first();
        $batch = DB::table('ai_batches')->where('id', $this->batchId)->where('workspace_id', $this->workspaceId)->first();

        if ($page === null || $batch === null || (string) $page->state !== 'queued') {
            return;
        }

        DB::table('ai_batch_pages')->where('id', $this->pageId)->update([
            'state' => 'running', 'attempts' => (int) $page->attempts + 1, 'started_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ai_batches')->where('id', $this->batchId)->where('state', 'queued')->update(['state' => 'running', 'updated_at' => now()]);

        $asset = DB::table('media_assets')
            ->where('id', (int) $page->media_asset_id)
            ->where('workspace_id', $this->workspaceId)
            ->whereNull('deleted_at')
            ->first();

        try {
            if ($asset === null) {
                throw new ProviderCallException('none', 'not-found');
            }

            $result = $extract->handle(
                $this->workspaceId,
                (int) $batch->menu_id,
                Storage::disk('local')->path((string) $asset->disk_path),
                ['purpose' => 'batch', 'batchId' => $this->batchId, 'page' => (int) $page->position],
            );

            DB::table('ai_batch_pages')->where('id', $this->pageId)->update([
                'state' => 'done', 'ai_artifact_id' => $result['id'], 'finished_at' => now(), 'updated_at' => now(),
            ]);
        } catch (ProviderCallException $exception) {
            DB::table('ai_batch_pages')->where('id', $this->pageId)->update([
                'state' => 'failed', 'failure_reason' => $exception->reason, 'finished_at' => now(), 'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            DB::table('ai_batch_pages')->where('id', $this->pageId)->update([
                'state' => 'failed', 'failure_reason' => 'exception', 'finished_at' => now(), 'updated_at' => now(),
            ]);
        }

        $finish->handle($this->batchId);
    }
}
