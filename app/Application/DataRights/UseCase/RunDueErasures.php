<?php

declare(strict_types=1);

namespace App\Application\DataRights\UseCase;

use App\Application\DataRights\Port\DataRequestRepositoryPort;
use App\Application\DataRights\Port\WorkspaceDataEraserPort;
use App\Application\DataRights\Port\WorkspaceDataExporterPort;
use App\Domain\DataRights\DataRequestState;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Penceresi dolan silmeleri yürütür — FF-226.
 *
 * ZAMANLAYICIDAN ÇAĞRILIR, dakikada bir değil GÜNDE bir: pencere gün
 * ölçeğindedir ve aynı sorguyu bin dört yüz kez koşturmanın anlamı yok
 * (`media:purge-trash` ile aynı gerekçe). Gece yarısından sonra, sahibin
 * ekrana bakmadığı saatte.
 *
 * BİR TALEBİN DÜŞMESİ DİĞERLERİNİ DURDURMAZ. Sebebi kendi satırına
 * yazılır; sessizce atlanan bir silme, hiç yapılmamış bir silmedir ve
 * kimse fark etmezdi.
 *
 * SÜRESİ DOLAN ARŞİVLER de aynı koşuda diskten kalkar: silme hakkının
 * yanında sonsuza kadar duran bir kopya, o hakkı anlamsız kılar.
 */
final readonly class RunDueErasures
{
    public function __construct(
        private DataRequestRepositoryPort $requests,
        private WorkspaceDataEraserPort $eraser,
        private WorkspaceDataExporterPort $exporter,
    ) {}

    /**
     * @return array{erased:int, failed:int, expired:int}
     */
    public function handle(): array
    {
        $erased = 0;
        $failed = 0;

        foreach ($this->requests->dueErasures() as $request) {
            try {
                $counts = $this->eraser->erase($request->workspaceId);
                $this->requests->markCompleted($request->id, $counts);
                $erased++;
            } catch (Throwable $exception) {
                Log::error('Veri silme talebi yürütülemedi.', [
                    'request_id' => $request->id,
                    'workspace_id' => $request->workspaceId,
                    'reason' => $exception->getMessage(),
                ]);

                $this->requests->markFailed($request->id, $exception->getMessage());
                $failed++;
            }
        }

        $expired = 0;

        foreach ($this->requests->expiredExports() as $request) {
            $path = $this->requests->artifactPath($request->id);

            if ($path !== null) {
                $this->exporter->forget($path);
            }

            $this->requests->markState($request->id, DataRequestState::Expired);
            $expired++;
        }

        return ['erased' => $erased, 'failed' => $failed, 'expired' => $expired];
    }
}
