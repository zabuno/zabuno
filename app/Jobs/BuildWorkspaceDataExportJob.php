<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\DataRights\Port\DataRequestRepositoryPort;
use App\Application\DataRights\Port\DataRightsNotifierPort;
use App\Application\DataRights\Port\WorkspaceDataExporterPort;
use App\Domain\DataRights\DataRequestState;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Arşivi kuyrukta üretir — FF-226 (`docs/138` §3).
 *
 * TEK DENEME (`$tries = 1`). Yarı yazılmış bir arşifin üstüne ikinci bir
 * deneme yazmak, ilkinin neden düştüğünü sonsuza kadar gizlerdi. Düşen
 * talep sebebiyle birlikte ekranda durur ve sahip yeniden ister — bu,
 * sessizce yeniden denenen ve yine düşen bir işten dürüsttür.
 *
 * HABER VERME ARŞİVİN ŞARTI DEĞİL. Posta taşıyıcısı yoksa ya da düşerse
 * arşiv yine hazırdır ve ekranda görünür (`docs/93`). Aksi hâlde bir posta
 * arızası, üretilmiş bir arşivi "başarısız" gösterirdi.
 */
final class BuildWorkspaceDataExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $workspaceId,
        public readonly int $requestId,
    ) {}

    public function handle(
        DataRequestRepositoryPort $requests,
        WorkspaceDataExporterPort $exporter,
        DataRightsNotifierPort $notifier,
    ): void {
        $request = $requests->find($this->requestId);

        if ($request === null || $request->state !== DataRequestState::Queued) {
            return;
        }

        $requests->markState($this->requestId, DataRequestState::Running);

        try {
            $artifact = $exporter->export($this->workspaceId, $this->requestId);
        } catch (Throwable $exception) {
            Log::error('Veri dışa aktarma arşivi üretilemedi.', [
                'request_id' => $this->requestId,
                'workspace_id' => $this->workspaceId,
                'reason' => $exception->getMessage(),
            ]);

            $requests->markFailed($this->requestId, $exception->getMessage());

            return;
        }

        $days = (int) config('data-rights.export.available_days', 7);
        $availableUntil = Carbon::now()->addDays($days < 1 ? 7 : $days);

        $requests->markExportReady(
            $this->requestId,
            $artifact->path,
            $artifact->bytes,
            $artifact->checksumSha256,
            $availableUntil->toDateTimeString(),
        );

        $ready = $requests->find($this->requestId);
        $email = $this->requesterEmail();

        if ($ready === null || $email === null) {
            return;
        }

        $minutes = (int) config('data-rights.export.link_minutes', 10);

        $requests->recordNotification($this->requestId, $notifier->notify(
            $ready,
            $email,
            /*
                E-POSTADAKİ ADRES İMZALIDIR VE KISA ÖMÜRLÜDÜR. Gelen kutusu
                yıllarca yaşar; bir çalışma alanının bütün verisine giden
                kalıcı bir bağlantı orada durmamalı. Süre dolduğunda sahip
                panelden yeni bir bağlantı alır.
            */
            URL::temporarySignedRoute(
                'workspace.data-export.download',
                Carbon::now()->addMinutes($minutes < 1 ? 10 : $minutes),
                ['workspace' => $this->workspaceId, 'request' => $this->requestId],
            ),
        ));
    }

    private function requesterEmail(): ?string
    {
        $email = DB::table('workspace_data_requests as r')
            ->leftJoin('users as u', 'u.id', '=', 'r.requested_by_user_id')
            ->where('r.id', $this->requestId)
            ->value('u.email');

        return is_string($email) && $email !== '' ? $email : null;
    }
}
