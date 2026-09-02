<?php

declare(strict_types=1);

namespace App\Http\Controllers\MenuCatalog;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tek ürünü "bugün tükendi" işaretler — `docs/82` (P1-04).
 *
 * Görünürlükten AYRI bir eksen: gizli bir ürün menüde yoktur, tükenmiş bir
 * ürün menüde vardır ama bugün alınamaz.
 */
final class UpdateMenuItemStockController extends Controller
{
    public function __construct(
        private readonly MenuCatalogApiContextPort $context,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $menuItem): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $itemContext = $this->context->menuItemContext($menuItem);

        if ($itemContext === null || $itemContext->workspaceId !== $workspace) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MenuManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate(['outOfStock' => ['required', 'boolean']]);

        DB::table('menu_items')->where('id', $menuItem)->update([
            'out_of_stock_since' => $validated['outOfStock'] ? now() : null,
            'updated_at' => now(),
        ]);

        return response()->json(['id' => $menuItem, 'outOfStock' => (bool) $validated['outOfStock']]);
    }
}
