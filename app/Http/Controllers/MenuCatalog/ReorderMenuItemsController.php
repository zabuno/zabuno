<?php

declare(strict_types=1);

namespace App\Http\Controllers\MenuCatalog;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Application\MenuCatalog\Exception\MenuCatalogIncompleteOrderException;
use App\Application\MenuCatalog\Exception\MenuCatalogTenantMismatchException;
use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\MenuCatalog\ReorderMenuItemsRequest;
use Illuminate\Http\JsonResponse;

/**
 * Bir kategorinin ürünlerini sıralar — `docs/73` (P0-01).
 *
 * TOPLU ve TAM: `unique(category_id, position)` yüzünden satırları tek tek
 * güncellemek yolun ortasında çakışır, kısmî bir liste ise listelenmeyen
 * satırları öngörülemez bir yere bırakır.
 */
final class ReorderMenuItemsController extends Controller
{
    public function __construct(
        private readonly MenuCatalogRepositoryPort $menuCatalog,
        private readonly MenuCatalogApiContextPort $context,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(ReorderMenuItemsRequest $request, int $workspace, int $category): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        /*
            İKİ AŞAMALI KAPI — depodaki diğer kontrolcülerle aynı dil.

            Görme izni yoksa 404: çalışma alanının VARLIĞI bile sızmamalı.
            Görme var ama yönetme yoksa 403: kaynak var, yetki yok — ve
            kullanıcının çıkış yolu farklıdır (erişim istemek).
        */
        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MenuManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $categoryContext = $this->context->categoryContext($category);

        if ($categoryContext === null || $categoryContext->workspaceId !== $workspace) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        /** @var list<int> $ids */
        $ids = array_map('intval', (array) $request->validated('menuItemIds'));

        try {
            $this->menuCatalog->reorderMenuItems($workspace, $category, $ids);
        } catch (MenuCatalogTenantMismatchException) {
            return response()->json(['message' => 'Not Found.'], 404);
        } catch (MenuCatalogIncompleteOrderException $exception) {
            // 422: istek biçimsel olarak doğru ama İÇERİĞİ eksik.
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['menuItemIds' => [$exception->getMessage()]],
            ], 422);
        }

        return response()->json(['categoryId' => $category, 'menuItemIds' => $ids]);
    }
}
