<?php

declare(strict_types=1);

namespace App\Http\Controllers\MenuCatalog;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Application\MenuCatalog\Exception\MenuCatalogTenantMismatchException;
use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kategoriyi ve içindeki satırları menüden çıkarır — `docs/73` (P0-01).
 *
 * Sezonluk "Yaz Menüsü" eylülde de duruyordu ve kaldırmanın yolu yoktu.
 */
final class DeleteCategoryController extends Controller
{
    public function __construct(
        private readonly MenuCatalogRepositoryPort $menuCatalog,
        private readonly MenuCatalogApiContextPort $context,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $category): JsonResponse
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

        try {
            $this->menuCatalog->deleteCategory($workspace, $category);
        } catch (MenuCatalogTenantMismatchException) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json(['id' => $category, 'deleted' => true]);
    }
}
