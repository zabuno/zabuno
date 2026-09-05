<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Dto\MediaBulkCandidate;
use App\Application\Media\Port\MediaBulkPort;
use App\Application\Media\Port\MediaQuotaPort;
use App\Application\Media\UseCase\PlanMediaBulkOperation;
use App\Domain\Authorization\Permission;
use App\Domain\Media\MediaBulkAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * KURU ÇALIŞMA UCU — kaynağın "Etki" adımı
 * (`docs/reference/panel-v3/MedyaModulu.dc.html`, "Toplu işlem").
 *
 * Bu uç HİÇBİR ŞEYİ DEĞİŞTİRMEZ; adı `plan`dır ve `POST` olması bir
 * çelişki değil, gövde gerekliliğidir: kapsam bin kimlikten oluşabilir ve
 * bin kimliği adres satırına yazmak hem sınırı aşar hem de kimlikleri
 * sunucu günlüklerine döker.
 *
 * YETKİSİZLİK BURADA 403 DEĞİLDİR ve bu bilinçlidir. Editör kalıcı silmeyi
 * planlayamaz ama kartın NEDEN kapalı olduğunu okumalı: cevap `allowed:
 * false` ve gereken izinle döner. 403 dönseydi ekran boş bir hata gösterir,
 * editör "ürün bunu yapamıyor" sanır ve yöneticisinden hiç istemezdi.
 * Gerçek kapı çalıştırma ucundadır.
 */
final class PlanMediaBulkOperationController extends Controller
{
    public function __construct(
        private readonly PlanMediaBulkOperation $plan,
        private readonly MediaBulkPort $bulk,
        private readonly MediaQuotaPort $quota,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in(array_column(MediaBulkAction::cases(), 'value'))],
            'scope' => ['required', 'string', Rule::in(['selected', 'workspace', 'folder'])],
            'assetIds' => ['array'],
            'assetIds.*' => ['integer'],
            'folderId' => ['nullable', 'integer'],
            'config' => ['array'],
        ]);

        $action = MediaBulkAction::from($validated['action']);
        $scope = $validated['scope'];
        $config = is_array($validated['config'] ?? null) ? $validated['config'] : [];

        /*
            KAPSAM BURADA DONDURULUR. "Süzgeçteki her şey" bir sorgu olarak
            değil, kimlik listesi olarak taşınır — kaynağın değişmez kuralı:
            "İş başladığı anda liste dondurulur. Sen çalışırken biri yeni
            dosya yüklerse o dosya bu işe girmez."
        */
        $assetIds = $scope === 'selected'
            ? array_map('intval', $validated['assetIds'] ?? [])
            : $this->bulk->idsForScope(
                $workspace,
                $scope,
                isset($validated['folderId']) ? (int) $validated['folderId'] : null,
                $action->operatesOnTrash(),
            );

        $plan = ($this->plan)($workspace, $action, $assetIds, $config);

        $allowed = $this->authorization->can($userId, $action->requiredPermission(), $workspace);
        $applyCount = count($plan['apply']);
        $quota = $this->quota->statusFor($workspace);

        return response()->json([
            'action' => $action->value,
            'allowed' => $allowed,
            'requiredPermission' => $allowed ? null : $action->requiredPermission()->value,
            'scope' => [
                'kind' => $scope,
                'count' => count($plan['candidates']),
                'totalBytes' => $plan['scopeBytes'],
            ],
            /*
                Dondurulmuş liste ÇAĞIRANA geri verilir ve çalıştırma onu
                aynen geri gönderir. Sunucuda saklamak bir oturum durumu
                yaratırdı: sahip iki sekmede iki ayrı iş kurduğunda
                hangisinin listesi olduğu belirsizleşirdi.
            */
            'snapshot' => ['assetIds' => array_map(
                static fn (MediaBulkCandidate $candidate): int => $candidate->id,
                $plan['candidates'],
            )],
            'applyCount' => $applyCount,
            'batchLimit' => $plan['batchLimit'],
            'remaining' => count($plan['overflow']),
            'skips' => $this->groupSkips($plan['skipped']),
            'skippedAssets' => $plan['skipped'],
            'impact' => [
                'reversible' => $action->isReversible(),
                // Geri alma penceresi UYDURULMAZ: kotanın kendi çöp
                // penceresidir ve kalıcı silmede hiç yoktur.
                'undoWindowDays' => $action->isReversible() ? $quota->trashRetentionDays : null,
                // Yeni SÜRÜM yalnız yeniden üreten eylemlerde açılır;
                // taşımak ve silmek sürüm açmaz.
                'newVersion' => in_array($action, [MediaBulkAction::Optimize, MediaBulkAction::Convert], true),
                'quotaBytesUsed' => $quota->originalBytesUsed,
                'quotaBytesLimit' => $quota->originalBytesLimit,
                /*
                    Kalıcı silme kotayı BOŞALTIR ve boşalacak bayt gerçek
                    dosya boyutlarının toplamıdır. Diğer eylemlerde kotanın
                    ne olacağını söylemiyoruz: yeniden üretimin ne kadar yer
                    tutacağı ancak kodlamadan SONRA bilinir ve bir tahmin
                    yazmak, bu ekranın tek sözünü ("sayılar gerçek") bozardı.
                */
                'quotaBytesFreed' => $action === MediaBulkAction::Purge
                    ? array_sum(array_map(
                        static fn (MediaBulkCandidate $candidate): int => $candidate->sizeBytes,
                        $plan['apply'],
                    ))
                    : null,
            ],
            /*
                Kaynağın iki eşiği: yıkıcı işlem HER ZAMAN yazılı onay
                ister; yıkıcı olmayan işlem yüz dosyayı aşınca ister.
            */
            'confirmation' => $action->isDestructive() || $applyCount > 100
                ? ['required' => true, 'word' => $action->confirmWord()]
                : ['required' => false, 'word' => null],
        ]);
    }

    /**
     * @param  list<array{id:int, name:string, reason:string}>  $skipped
     * @return list<array{reason:string, count:int}>
     */
    private function groupSkips(array $skipped): array
    {
        $counts = [];

        foreach ($skipped as $skip) {
            $counts[$skip['reason']] = ($counts[$skip['reason']] ?? 0) + 1;
        }

        $out = [];

        foreach ($counts as $reason => $count) {
            $out[] = ['reason' => (string) $reason, 'count' => $count];
        }

        return $out;
    }
}
