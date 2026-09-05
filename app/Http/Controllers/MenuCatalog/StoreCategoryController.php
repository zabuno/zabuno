<?php

declare(strict_types=1);

namespace App\Http\Controllers\MenuCatalog;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Dto\MenuAuditEntry;
use App\Application\MenuCatalog\Exception\MenuCatalogTenantMismatchException;
use App\Application\MenuCatalog\Port\MenuAuditPort;
use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Domain\MenuCatalog\MenuAuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\MenuCatalog\StoreCategoryRequest;
use Illuminate\Http\JsonResponse;

final class StoreCategoryController extends Controller
{
    public function __construct(
        private readonly MenuCatalogRepositoryPort $menuCatalog,
        private readonly AuthorizationPort $authorization,
        private readonly MenuAuditPort $audit,
    ) {}

    public function __invoke(StoreCategoryRequest $request, int $workspace, int $menu): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MenuManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        try {
            $category = $this->menuCatalog->addCategory($workspace, $menu, (string) $request->validated('name'));
        } catch (MenuCatalogTenantMismatchException) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        /*
            DENETİM İZİ (FF-154). Kategori misafirin gördüğü YAPIDIR; menüde
            bir gün "Alkollü İçecekler" başlığı belirdiğinde bunu kimin
            açtığı sorulur. Hacim ihmal edilebilir — bir menüde on kadar
            kategori vardır ve her biri bir kez açılır.
        */
        $this->audit->record(MenuAuditEntry::forCategory(
            $workspace,
            $category->menuId,
            $category->id,
            $category->name,
            MenuAuditAction::CategoryAdded,
            null,
            $category->name,
            $userId,
        ));

        return response()->json([
            'id' => $category->id,
            'menuId' => $category->menuId,
            'name' => $category->name,
            'position' => $category->position,
        ], 201);
    }
}
