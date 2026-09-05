<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MediaAuditPort;
use App\Application\Media\Port\MediaFormatSupportPort;
use App\Application\Media\UseCase\ConvertMediaAssetsToFormat;
use App\Domain\Authorization\Permission;
use App\Domain\Media\ConversionTargetCatalogue;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DÖNÜŞTÜR — seçilen dosyaları hedef biçime çevirir (`docs/108` §6.3).
 *
 * Kaynağın cümlesi: "Aslı korunur, dönüşen dosya yeni sürüm olur." Bu uç
 * o cümleyi kendi başına uygulamaz; var olan `ReprocessMediaAsset` hattını
 * bir hedef biçimle çağırır ve o hattın bütün güvenceleri kendiliğinden
 * geçerli olur — asıl korunur, her varlık YENİ SÜRÜM açar, hiçbir satır
 * silinmez, başarısızlık varlığı `failed` yapmaz ve iş kaydı sebebi taşır.
 *
 * DESTEKLENMEYEN HEDEF 422 İLE REDDEDİLİR. "Ekranda gizle, arka uçta
 * kabul et" iki başlı bir dürüstlüktür: bugün ekran gizler, yarın başka
 * bir çağıran ister ve ürün sessizce yanlış iş yapar. Cevap sebebi de
 * taşır (`limitation`), çünkü "olmadı" bir cevap değildir.
 *
 * HIZ SINIRI `throttle:2,1` — toplu yeniden üretimle aynı. Tek çağrı
 * onlarca dosyayı kodlar ve AVIF kodlaması JPEG'den belirgin biçimde
 * yavaştır.
 *
 * BAŞARILI ÇAĞRI 200 döner ve İÇİNDE başarısızlığı sayar. 502 dönmek
 * yanlış olurdu: kırk dokuz dosya dönüşmüşken isteğin tamamını başarısız
 * saymak, sahibi olan işi de olmamış sanmaya iter — ve tekrar bastırır
 * (`ReprocessMediaBatchController` ile aynı gerekçe).
 */
final class ConvertMediaController extends Controller
{
    public function __construct(
        private readonly ConvertMediaAssetsToFormat $convert,
        private readonly MediaFormatSupportPort $support,
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

        $format = strtolower(trim((string) $request->input('format', '')));

        if (! ConversionTargetCatalogue::canonical()->has($format)) {
            return response()->json([
                'message' => 'Unknown format.',
                'limitation' => 'unknown-format',
            ], 422);
        }

        if (! $this->support->supports($format)) {
            return response()->json([
                'message' => 'This format cannot be produced on this installation.',
                'limitation' => $this->support->limitation($format),
            ], 422);
        }

        /** @var list<int> $assetIds */
        $assetIds = array_values(array_filter(
            array_map('intval', (array) $request->input('assetIds', [])),
            static fn (int $id): bool => $id > 0,
        ));

        if ($assetIds === []) {
            return response()->json([
                'message' => 'No file selected.',
                'limitation' => 'no-selection',
            ], 422);
        }

        $outcome = ($this->convert)(
            $workspace,
            $format,
            $assetIds,
            (int) config('media-slots.regeneration.batch_limit', 25),
        );

        /*
            Denetim izi varlık BAŞINA yazılır, iş başına değil: sahip altı ay
            sonra "bu fotoğrafın biçimi neden değişmiş?" diye sorduğunda
            cevabı o fotoğrafın kendi izinde bulmalı, "bir yerde toplu bir
            iş çalıştı" cümlesinde değil.
        */
        foreach ($outcome['assetIds'] as $assetId) {
            $this->audit->record($workspace, $assetId, 'reprocessed', $userId);
        }

        return response()->json([
            'format' => $format,
            'processed' => $outcome['processed'],
            'succeeded' => $outcome['succeeded'],
            'failed' => $outcome['failed'],
            'skipped' => $outcome['skipped'],
            // Sınıra takılan SEÇİLİ dosyalar: sahip düğmeye yeniden
            // bastığında kaldığı yerden devam eder.
            'remaining' => $outcome['remaining'],
        ]);
    }
}
