<?php

declare(strict_types=1);

namespace App\Http\Controllers\MenuCatalog;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MediaRepositoryPort;
use App\Application\Media\Port\MenuMediaPort;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ürüne fotoğraf bağlar — `docs/77` (P0-04).
 *
 * Bağ SÜRÜME yapılır: sahip fotoğrafı sonradan düzenlerse yayınlanmış menü
 * hâlâ onayladığı sürümü gösterir.
 */
final class BindMenuItemImageController extends Controller
{
    public function __construct(
        private readonly MenuMediaPort $menuMedia,
        private readonly MenuCatalogApiContextPort $context,
        private readonly AuthorizationPort $authorization,
        private readonly MediaRepositoryPort $media,
    ) {}

    public function __invoke(Request $request, int $workspace, int $menuItem): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MenuManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $itemContext = $this->context->menuItemContext($menuItem);

        if ($itemContext === null || $itemContext->workspaceId !== $workspace) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $validated = $request->validate([
            'mediaAssetId' => ['present', 'nullable', 'integer', 'min:1'],
        ]);

        $mediaAssetId = $validated['mediaAssetId'] === null ? null : (int) $validated['mediaAssetId'];

        if (! $this->menuMedia->bindMenuItemImage($workspace, $menuItem, $mediaAssetId)) {
            // 422: istek biçimsel olarak doğru, görsel HENÜZ kullanılabilir
            // değil. Sahip beklemeli — ve bunu okuyabilmeli.
            return response()->json([
                'message' => $this->refusalMessage($workspace, $mediaAssetId),
            ], 422);
        }

        return response()->json([
            'menuItemId' => $menuItem,
            'mediaAssetId' => $mediaAssetId,
        ]);
    }

    /**
     * Ret cümlesi: VAAT DEĞİL, kayda geçmiş sebep (FF-150).
     *
     * Eskiden tek bir cümle vardı: "İşlenmesi bitince yeniden deneyin."
     * Sunucuda virüs tarayıcı kurulu değilken bu cümle OLMAYACAK bir şeyi
     * vaat ediyordu — dosya `scanning` durumunda süresiz bekliyor, işleme
     * hiç başlamıyor. Sahip menü ekranında saatlerce yeniden deniyor ve
     * her seferinde aynı cümleyi okuyor.
     *
     * Sebep burada ÜRETİLMEZ; boru hattının kendi kaydından okunur
     * (`media_processing_jobs.failure_reason`, `held`/`failed`). Böylece
     * ekranın söylediği ile sistemin yaptığı tek kaynaktan gelir ve bir
     * gün ayrışamaz.
     *
     * Kayıtlı sebep yoksa eski cümle kalır: o durumda dosya gerçekten
     * ilerliyordur (`accepted`/`processing`) ve beklemek doğru tavsiyedir.
     *
     * KİRACI SINIRI: sebep, yalnız İSTENEN çalışma alanının varlığı için
     * okunur. `find()` kimlikle çalışır ve kiracı sormaz; başka bir
     * kiracının varlığına ait bir cümleyi buraya yazmak, o kiracının
     * dosyasının var olduğunu ele verirdi.
     */
    private function refusalMessage(int $workspaceId, ?int $mediaAssetId): string
    {
        $fallback = 'Bu görsel henüz kullanıma hazır değil. İşlenmesi bitince yeniden deneyin.';

        if ($mediaAssetId === null) {
            return $fallback;
        }

        $asset = $this->media->find($mediaAssetId);

        if ($asset === null || $asset->workspaceId !== $workspaceId) {
            return $fallback;
        }

        $reason = trim((string) ($asset->statusReason ?? ''));

        return $reason === '' ? $fallback : $reason;
    }
}
