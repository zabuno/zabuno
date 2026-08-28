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
use App\Http\Requests\MenuCatalog\ReorderCategoriesRequest;
use Illuminate\Http\JsonResponse;

/** Menünün kategorilerini sıralar — `docs/73` (P0-01). */
final class ReorderCategoriesController extends Controller
{
    public function __construct(
        private readonly MenuCatalogRepositoryPort $menuCatalog,
        private readonly MenuCatalogApiContextPort $context,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(ReorderCategoriesRequest $request, int $workspace, int $menu): JsonResponse
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

        /** @var list<int> $ids */
        $ids = array_map('intval', (array) $request->validated('categoryIds'));

        try {
            $this->menuCatalog->reorderCategories($workspace, $menu, $ids);
        } catch (MenuCatalogTenantMismatchException) {
            return response()->json(['message' => 'Not Found.'], 404);
        } catch (MenuCatalogIncompleteOrderException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['categoryIds' => [$exception->getMessage()]],
            ], 422);
        }

        return response()->json(['menuId' => $menu, 'categoryIds' => $ids]);
    }
}
