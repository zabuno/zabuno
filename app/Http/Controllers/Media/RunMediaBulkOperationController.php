<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MediaBulkPort;
use App\Application\Media\UseCase\RunMediaBulkOperation;
use App\Domain\Authorization\Permission;
use App\Domain\Media\MediaBulkAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * TOPLU İŞİ ÇALIŞTIRAN UÇ — kaynağın "Sonuç" adımı.
 *
 * Üç kapı sırayla:
 *
 * 1. **Yetki.** Kuru çalışma yetkisizi bilgilendirir, bu uç DURDURUR:
 *    403 ve hiçbir satır değişmez.
 * 2. **Yazılı onay.** Yıkıcı işlemde onay kutusu yetmez; sahip kelimeyi
 *    yazar. Yanlış ya da eksik kelime 422'dir ve yine hiçbir satır
 *    değişmez. Karşılaştırma TAM eşleşmedir: küçük/büyük harf esnekliği
 *    tanımak, "kalıcı sil" yazan birinin bin dosyayı kaybetmesi demek
 *    olurdu ve Türkçede `strtoupper('i')` zaten `İ` vermez.
 * 3. **İşlem anahtarı.** Aynı anahtar iki kez çalışmaz. Kaynağın cümlesi:
 *    "Aynı işlem anahtarıyla iş iki kez çalıştırılamaz."
 *
 * Cevap 200 döner ve İÇİNDE başarısızlığı sayar. 502 dönmek yanlış olurdu:
 * elli dosyanın kırk dokuzu işlenmişken isteğin tamamını başarısız saymak,
 * sahibi olan işi de olmamış sanmaya iter — ve tekrar bastırır (aynı
 * gerekçe `ReprocessMediaBatchController` içinde de yazılı).
 */
final class RunMediaBulkOperationController extends Controller
{
    public function __construct(
        private readonly RunMediaBulkOperation $run,
        private readonly MediaBulkPort $bulk,
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
            'operationKey' => ['required', 'string', 'max:64'],
            'assetIds' => ['required', 'array', 'min:1'],
            'assetIds.*' => ['integer'],
            'config' => ['array'],
            'confirm' => ['nullable', 'string'],
        ]);

        $action = MediaBulkAction::from($validated['action']);

        if (! $this->authorization->can($userId, $action->requiredPermission(), $workspace)) {
            return response()->json([
                'message' => 'Forbidden.',
                'requiredPermission' => $action->requiredPermission()->value,
            ], 403);
        }

        $assetIds = array_values(array_unique(array_map('intval', $validated['assetIds'])));
        $config = is_array($validated['config'] ?? null) ? $validated['config'] : [];

        if ($action->isDestructive() && ($validated['confirm'] ?? '') !== $action->confirmWord()) {
            return response()->json([
                'message' => 'Confirmation required.',
                'errors' => ['confirm' => [$action->confirmWord()]],
            ], 422);
        }

        /*
            Anahtar İŞTEN ÖNCE aranır. Sonra aramak, iki eşzamanlı isteğin
            ikisinin de çalışıp yalnız ikincisinin kaydının düşmemesi
            demek olurdu — bin dosya iki kez işlenirdi ve kayıt bunu hiç
            göstermezdi.
        */
        if ($this->bulk->operationExists($workspace, $validated['operationKey'])) {
            return response()->json([
                'operationKey' => $validated['operationKey'],
                'action' => $action->value,
                'replayed' => true,
                'applied' => 0,
                'skipped' => 0,
                'failed' => 0,
                'remaining' => 0,
                'results' => [],
            ]);
        }

        $outcome = ($this->run)($workspace, $action, $assetIds, $config, $userId);

        /*
            Kayıt iş BİTTİKTEN sonra yazılır ve sayıları taşır. Kısıt yine
            de yarışı kapatır: iki eşzamanlı istek arasında ikincisinin
            yazması reddedilir ve o istek "tekrar oynatıldı" döner.
        */
        $recorded = $this->bulk->recordOperation(
            $workspace,
            $validated['operationKey'],
            $action->value,
            $validated['scope'],
            [
                'planned' => count($assetIds),
                'applied' => $outcome['applied'],
                'skipped' => $outcome['skipped'],
                'failed' => $outcome['failed'],
            ],
            $userId,
        );

        return response()->json([
            'operationKey' => $validated['operationKey'],
            'action' => $action->value,
            'replayed' => $recorded === null,
            'applied' => $outcome['applied'],
            'skipped' => $outcome['skipped'],
            'failed' => $outcome['failed'],
            // Sınıra takılan dosyalar: sahip düğmeye yeniden bastığında
            // kaldığı yerden devam eder.
            'remaining' => $outcome['remaining'],
            'results' => $outcome['results'],
        ]);
    }
}
