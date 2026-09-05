<?php

declare(strict_types=1);

namespace App\Http\Controllers\MenuCatalog;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Application\MenuCatalog\Dto\MenuAuditEntry;
use App\Application\MenuCatalog\Exception\MenuCatalogTenantMismatchException;
use App\Application\MenuCatalog\Port\MenuAuditPort;
use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Domain\MenuCatalog\MenuAuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\MenuCatalog\UpdateMenuItemVisibilityRequest;
use Illuminate\Http\JsonResponse;

final class UpdateMenuItemVisibilityController extends Controller
{
    public function __construct(
        private readonly MenuCatalogRepositoryPort $menuCatalog,
        private readonly MenuCatalogApiContextPort $context,
        private readonly AuthorizationPort $authorization,
        private readonly MenuAuditPort $audit,
    ) {}

    public function __invoke(UpdateMenuItemVisibilityRequest $request, int $workspace, int $menuItem): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MenuManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $menuItemContext = $this->context->menuItemContext($menuItem);

        if ($menuItemContext === null || $menuItemContext->workspaceId !== $workspace) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $isVisible = (bool) $request->validated('isVisible');

        try {
            $summary = $this->menuCatalog->updateMenuItemVisibility($workspace, $menuItem, $isVisible);
        } catch (MenuCatalogTenantMismatchException) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        /*
            DENETİM İZİ (FF-154). Görünürlük misafirin gördüğünü DOĞRUDAN
            değiştirir: gizlenen ürün menüde yoktur. "Beyti neden menüde
            görünmüyor?" sorusunun cevabı bugüne kadar hiçbir yerde yoktu.

            Değişmeyen değer yazılmaz — aynı düğmeye iki kez basmak bir
            olay değildir.
        */
        if ($menuItemContext->isVisible !== $summary->isVisible) {
            $this->audit->record(MenuAuditEntry::forItem(
                $workspace,
                $menuItemContext->menuId,
                $menuItem,
                $menuItemContext->productName,
                MenuAuditAction::ItemVisibilityChanged,
                MenuAuditEntry::visibility($menuItemContext->isVisible),
                MenuAuditEntry::visibility($summary->isVisible),
                $userId,
            ));
        }

        return response()->json([
            'id' => $summary->id,
            'categoryId' => $summary->categoryId,
            'productId' => $summary->productId,
            'isVisible' => $summary->isVisible,
            'position' => $summary->position,
        ]);
    }
}
