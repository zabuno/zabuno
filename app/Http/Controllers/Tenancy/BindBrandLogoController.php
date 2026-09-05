<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenancy;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MediaRepositoryPort;
use App\Application\Media\Port\MenuMediaPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Markanın logosunu bağlar — `docs/77` (P0-03 logo tamamlaması).
 *
 * Logo misafirin gördüğü ilk şeydir; menüde ada eşlik eder ve yayın
 * anında snapshot'a donar.
 */
final class BindBrandLogoController extends Controller
{
    public function __construct(
        private readonly MenuMediaPort $menuMedia,
        private readonly AuthorizationPort $authorization,
        private readonly MediaRepositoryPort $media,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::WorkspaceManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $brandId = DB::table('brands')->where('workspace_id', $workspace)->value('id');

        if ($brandId === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $validated = $request->validate([
            'mediaAssetId' => ['present', 'nullable', 'integer', 'min:1'],
        ]);

        $mediaAssetId = $validated['mediaAssetId'] === null ? null : (int) $validated['mediaAssetId'];

        if (! $this->menuMedia->bindBrandLogo($workspace, (int) $brandId, $mediaAssetId)) {
            // 422: istek biçimsel olarak doğru, görsel HENÜZ kullanılabilir
            // değil. Sahip beklemeli — ve bunu okuyabilmeli.
            return response()->json([
                'message' => $this->refusalMessage($workspace, $mediaAssetId),
            ], 422);
        }

        return response()->json(['brandId' => (int) $brandId, 'mediaAssetId' => $mediaAssetId]);
    }

    /**
     * Ret cümlesi: VAAT DEĞİL, kayda geçmiş sebep (FF-151).
     *
     * FF-150 aynı yalanı menü kaleminde susturdu, logoda unuttu. Oysa logo
     * misafirin gördüğü İLK şeydir ve sahibi onu kurulumun daha ilk yarım
     * saatinde bağlamaya çalışır: yani bu cümleyi çoğu sahip menü
     * kalemininkinden ÖNCE okuyor. Sunucuda virüs tarayıcı kurulu değilken
     * "İşlenmesi bitince yeniden deneyin" olmayacak bir anı işaret eder —
     * dosya `scanning` durumunda süresiz bekler, işleme hiç başlamaz.
     *
     * Sebep burada ÜRETİLMEZ; boru hattının kendi kaydından okunur
     * (`media_processing_jobs.failure_reason`, `held`/`failed`). Ekranın
     * söylediği ile sistemin yaptığı tek kaynaktan gelir ve bir gün
     * ayrışamaz. Cümle `BindMenuItemImageController` ile AYNIDIR: sahip aynı
     * gerçeği iki ekranda iki farklı şekilde okumamalı.
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
