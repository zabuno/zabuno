<?php

declare(strict_types=1);

namespace App\Http\Controllers\MenuCatalog;

use App\Application\Authorization\Port\AuthorizationPort;
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
                'message' => 'Bu görsel henüz kullanıma hazır değil. İşlenmesi bitince yeniden deneyin.',
            ], 422);
        }

        return response()->json([
            'menuItemId' => $menuItem,
            'mediaAssetId' => $mediaAssetId,
        ]);
    }
}
