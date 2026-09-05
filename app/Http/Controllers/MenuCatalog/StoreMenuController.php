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
use App\Http\Requests\MenuCatalog\StoreMenuRequest;
use Illuminate\Http\JsonResponse;

final class StoreMenuController extends Controller
{
    public function __construct(
        private readonly MenuCatalogRepositoryPort $menuCatalog,
        private readonly MenuCatalogApiContextPort $context,
        private readonly AuthorizationPort $authorization,
        private readonly MenuAuditPort $audit,
    ) {}

    public function __invoke(StoreMenuRequest $request, int $workspace, int $location): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MenuManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($this->context->locationWorkspaceId($location) !== $workspace) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        try {
            $menu = $this->menuCatalog->createDraftMenu($workspace, $location, (string) $request->validated('name'));
        } catch (MenuCatalogTenantMismatchException) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        /*
            DENETİM İZİ (FF-154). Menü açmak düşük hacimli ama şubenin
            gününü bölen bir karardır (`docs/109` §7.1); izin ilk satırı
            olarak da işe yarar — sonraki her kayıt bir menüye bağlanır.
        */
        $this->audit->record(MenuAuditEntry::forMenu(
            $workspace,
            $menu->id,
            $menu->name,
            MenuAuditAction::MenuCreated,
            null,
            $menu->name,
            $userId,
        ));

        return response()->json([
            'id' => $menu->id,
            'workspaceId' => $menu->workspaceId,
            'locationId' => $menu->locationId,
            'name' => $menu->name,
            'state' => $menu->state,
            'sortOrder' => $menu->sortOrder,
        ], 201);
    }
}
