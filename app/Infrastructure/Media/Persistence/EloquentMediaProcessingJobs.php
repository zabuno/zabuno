<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Persistence;

use App\Application\Media\Port\MediaProcessingJobPort;
use Illuminate\Support\Facades\DB;

/**
 * Kuyruğun okuma yüzeyi — `media_processing_jobs` tablosunun kendisi.
 *
 * Tabloda YÜZDE SÜTUNU YOKTUR. Bu bir eksiklik değil bir gerçektir ve
 * burada uydurulmaz: çalışan işin ilerlemesi `null` döner, biten işin
 * ilerlemesi bellidir (`1.0`). Ekran `null` gördüğünde belirsiz bir şerit
 * çizer; sahte bir "%40" ise sahibi önce bekletir, sonra yanıltır.
 */
final class EloquentMediaProcessingJobs implements MediaProcessingJobPort
{
    private const TABLE = 'media_processing_jobs';

    /** İş kapandı sayılan durumlar — kalan her şey hâlâ akıştadır. */
    private const FINISHED_STATES = ['succeeded', 'failed', 'held'];

    /**
     * @return array<int, array{
     *     id:int,
     *     mediaAssetId:int,
     *     assetName:?string,
     *     kind:string,
     *     state:string,
     *     attempts:int,
     *     failureReason:?string,
     *     finished:bool,
     *     progress:?float,
     *     startedAt:?string,
     *     finishedAt:?string
     * }>
     */
    public function recent(int $workspaceId, int $limit = 30): array
    {
        /*
            Dosyanın ADI satırda yazar. Yalnız kimlik numarası yazsaydı
            sahip "hangi fotoğraf?" sorusunu ancak kütüphaneye dönüp
            arayarak cevaplayabilirdi.

            `leftJoin`, çünkü varlık silinmiş olabilir ve İŞ KAYDI ondan
            uzun yaşar: bir şeyin başarısız olduğu gerçeği, dosyanın artık
            orada olmamasından bağımsızdır.
        */
        $rows = DB::table(self::TABLE.' as j')
            ->leftJoin('media_assets as a', 'a.id', '=', 'j.media_asset_id')
            ->where('j.workspace_id', $workspaceId)
            ->orderByDesc('j.id')
            ->limit($limit)
            ->get([
                'j.id',
                'j.media_asset_id',
                'j.kind',
                'j.state',
                'j.attempts',
                'j.failure_reason',
                'j.started_at',
                'j.finished_at',
                'a.alt_text',
                'a.original_name',
            ]);

        return $rows->map(static function (object $row): array {
            $finished = in_array((string) $row->state, self::FINISHED_STATES, true);

            // Sahibin verdiği ad önce; yoksa dosyanın kendi adı. İkisi de
            // yoksa `null` — ekran "adsız görsel" der, kimlik numarası değil.
            $name = $row->alt_text ?? $row->original_name;

            return [
                'id' => (int) $row->id,
                'mediaAssetId' => (int) $row->media_asset_id,
                'assetName' => $name === null || $name === '' ? null : (string) $name,
                'kind' => (string) $row->kind,
                'state' => (string) $row->state,
                'attempts' => (int) $row->attempts,
                'failureReason' => $row->failure_reason === null ? null : (string) $row->failure_reason,
                'finished' => $finished,
                'progress' => $finished ? 1.0 : null,
                'startedAt' => $row->started_at === null ? null : (string) $row->started_at,
                'finishedAt' => $row->finished_at === null ? null : (string) $row->finished_at,
            ];
        })->all();
    }

    /**
     * @return array{pending:int, running:int, succeeded:int, failed:int, held:int, total:int}
     */
    public function counts(int $workspaceId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('workspace_id', $workspaceId)
            ->groupBy('state')
            ->selectRaw('state, count(*) as job_count')
            ->get();

        $counts = [
            'pending' => 0,
            'running' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'held' => 0,
        ];

        $total = 0;

        foreach ($rows as $row) {
            $state = (string) $row->state;
            $count = (int) $row->job_count;
            $total += $count;

            // Bilinmeyen bir durum sessizce SIFIRLANMAZ; toplamda kalır.
            // Sayaçların toplamı ile toplam iş sayısının tutmaması, yeni bir
            // durumun eklendiğini ve ekranın güncellenmediğini söyler.
            if (array_key_exists($state, $counts)) {
                $counts[$state] = $count;
            }
        }

        $counts['total'] = $total;

        return $counts;
    }
}
