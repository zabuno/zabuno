<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ai;

use App\Application\Ai\Exception\ProviderCallException;
use App\Application\Ai\UseCase\ExtractMenuFromImage;
use App\Application\Authorization\Port\AuthorizationPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * TOPLU fotoğraf okuma — `docs/96` Faz 3.
 *
 * Gerçek durum: bir restoranın menüsü tek bir fotoğrafa sığmaz. Dört
 * sayfalık bir menüyü tek tek okutmak, sahibin aynı işi dört kez yapması
 * demekti — ve her seferinde inceleme ekranını kapatıp yeniden açması.
 *
 * KISMİ BAŞARISIZLIK TOLERE EDİLİR. Dördüncü fotoğraf bulanıksa, ilk üçün
 * sonucu ÇÖPE GİTMEZ: o satır bir hata sebebiyle işaretlenir, diğerleri
 * incelemeye girer. Tümünü reddetmek, sahibi hiçbir şey kazanmadan baştan
 * başlatırdı.
 *
 * SAYI SINIRLI (10). Her fotoğraf dış bir sağlayıcıya para ödetir ve
 * sınırsız bir liste, faturayı tek bir isteğe bıraktırırdı. Sınır rota
 * hız sınırının (throttle) yerine geçmez, ona EKLENİR.
 */
final class StoreBulkMenuAiImportController extends Controller
{
    private const MAX_PHOTOS = 10;

    public function __construct(
        private readonly ExtractMenuFromImage $extract,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $menu): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MenuManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $availability = $this->extract->availability($workspace);

        if (! $availability->isAvailable()) {
            return response()->json([
                'message' => 'Fotoğraftan menü okuma şu anda kullanılamıyor.',
                'reason' => $availability->value,
            ], 503);
        }

        $validated = $request->validate([
            'mediaAssetIds' => ['required', 'array', 'min:1', 'max:'.self::MAX_PHOTOS],
            'mediaAssetIds.*' => ['integer', 'min:1'],
        ]);

        $menuRow = DB::table('menus')->where('id', $menu)->where('workspace_id', $workspace)->first();

        if ($menuRow === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $results = [];

        foreach (array_values(array_unique($validated['mediaAssetIds'])) as $mediaAssetId) {
            $asset = DB::table('media_assets')
                ->where('id', (int) $mediaAssetId)
                ->where('workspace_id', $workspace)
                ->whereNull('deleted_at')
                ->first();

            if ($asset === null) {
                // Başka bir tenant'ın (ya da silinmiş) görselini istemek
                // 404 DEĞİL, satır düzeyinde bir reddir: kalan fotoğrafların
                // okunmasına engel olmamalı, ama sessizce de geçilmemeli.
                $results[] = [
                    'mediaAssetId' => (int) $mediaAssetId,
                    'error' => 'not-found',
                ];

                continue;
            }

            try {
                $result = $this->extract->handle(
                    $workspace,
                    $menu,
                    Storage::disk('local')->path((string) $asset->disk_path),
                );
            } catch (ProviderCallException $exception) {
                $results[] = [
                    'mediaAssetId' => (int) $mediaAssetId,
                    'error' => $exception->reason,
                ];

                continue;
            }

            $results[] = [
                'mediaAssetId' => (int) $mediaAssetId,
                'id' => $result['id'],
                'uncertainFieldCount' => count($result['artifact']->uncertainFields()),
                'usedFallback' => $result['artifact']->usedFallback,
            ];
        }

        return response()->json(['results' => $results], 201);
    }
}
