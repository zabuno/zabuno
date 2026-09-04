<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MediaAuditPort;
use App\Application\Media\UseCase\RegenerateWorkspaceDerivatives;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Kuralı değiştirdim, eskiler ne olacak?" — TOPLU yeniden üretim
 * (`docs/108` §4, kaynak ekranı "Boyut motoru").
 *
 * Bu uç YENİ BİR İŞLEME HATTI AÇMAZ: var olan `ReprocessMediaAsset` her
 * varlık için sırayla çalışır. Dolayısıyla var olan bütün güvenceler
 * kendiliğinden geçerlidir — asıl korunur, her varlık YENİ SÜRÜM açar,
 * hiçbir satır silinmez, başarısızlık varlığı `failed` yapmaz ve iş kaydı
 * sebebi taşır.
 *
 * HIZ SINIRI tek varlık ucundan (`throttle:10,1`) daha SIKIDIR: tek çağrı
 * bütün hazır dosyaları işler, on kat işi on kat sık çalıştırmaya izin
 * vermek anlamsızdı.
 *
 * Cevap 200 döner ve İÇİNDE başarısızlığı sayar. 502 dönmek yanlış olurdu:
 * elli dosyanın kırk dokuzu yenilenmişken isteğin tamamını başarısız
 * saymak, sahibi olan işi de olmamış sanmaya iter — ve tekrar bastırır.
 */
final class ReprocessMediaBatchController extends Controller
{
    public function __construct(
        private readonly RegenerateWorkspaceDerivatives $regenerate,
        private readonly AuthorizationPort $authorization,
        private readonly MediaAuditPort $audit,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MediaManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $outcome = ($this->regenerate)(
            $workspace,
            (int) config('media-slots.regeneration.batch_limit', 25),
        );

        /*
            Denetim izi varlık BAŞINA yazılır, toplu iş başına değil: sahip
            altı ay sonra "bu fotoğrafın ölçüleri neden değişmiş?" diye
            sorduğunda cevabı o fotoğrafın kendi izinde bulmalı, "bir yerde
            toplu bir iş çalıştı" cümlesinde değil.
        */
        foreach ($outcome['assetIds'] as $assetId) {
            $this->audit->record($workspace, $assetId, 'reprocessed', $userId);
        }

        return response()->json([
            'processed' => $outcome['processed'],
            'succeeded' => $outcome['succeeded'],
            'failed' => $outcome['failed'],
            'skipped' => $outcome['skipped'],
            // Sınıra takılan dosyalar: sahip düğmeye yeniden bastığında
            // kaldığı yerden devam eder.
            'remaining' => $outcome['remaining'],
        ]);
    }
}
