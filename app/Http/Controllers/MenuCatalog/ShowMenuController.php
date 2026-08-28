<?php

declare(strict_types=1);

namespace App\Http\Controllers\MenuCatalog;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MenuMediaPort;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Domain\MenuCatalog\StockState;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ShowMenuController extends Controller
{
    public function __construct(
        private readonly MenuCatalogRepositoryPort $menuCatalog,
        private readonly MenuCatalogApiContextPort $context,
        private readonly AuthorizationPort $authorization,
        private readonly MenuMediaPort $menuMedia,
    ) {}

    public function __invoke(Request $request, int $workspace, int $location): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ($this->context->locationWorkspaceId($location) !== $workspace) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $menuId = $this->context->menuIdForLocation($workspace, $location);

        if ($menuId === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $tree = $this->menuCatalog->getDraftTree($workspace, $menuId);

        if ($tree === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        // Bağlı görseller TEK sorguda okunur: satır başına sorgu, kırk
        // ürünlük bir menüde kırk gidiş dönüş demekti (`docs/78`).
        // "Bugün" ŞUBENİN saat diliminde bir gündür.
        $timezone = (string) (DB::table('locations')->where('id', $location)->value('timezone') ?: 'UTC');

        $menuItemIds = [];

        foreach ($tree->categories as $category) {
            foreach ($category['items'] as $item) {
                $menuItemIds[] = (int) $item['id'];
            }
        }

        $attached = $this->menuMedia->attachedAssetIds($workspace, $menuItemIds);

        return response()->json([
            'id' => $tree->id,
            'workspaceId' => $tree->workspaceId,
            'locationId' => $tree->locationId,
            'name' => $tree->name,
            'state' => $tree->state,
            'categories' => array_map(static fn (array $category): array => [
                'id' => $category['id'],
                'menuId' => $tree->id,
                'name' => $category['name'],
                'position' => $category['position'],
                'menuItems' => array_map(static fn (array $item): array => [
                    'id' => $item['id'],
                    'categoryId' => $category['id'],
                    'productId' => $item['productId'],
                    'productName' => $item['productName'],
                    'priceMinorAmount' => $item['priceMinorAmount'],
                    'currencyCode' => $item['currencyCode'],
                    'position' => $item['position'],
                    'isVisible' => $item['isVisible'],
                    'allergens' => $item['allergens'],
                    'description' => $item['description'] ?? null,
                    'imageMediaAssetId' => $attached[$item['id']] ?? null,
                    // "Bugün tükendi" GÖRÜNÜRLÜKTEN ayrı bir eksendir
                    // (`docs/82`); panel ikisini karıştırmamalı.
                    'outOfStock' => StockState::isOutOfStockNow(
                        $item['outOfStockSince'] ?? null,
                        $timezone,
                    ),
                ], $category['items']),
            ], $tree->categories),
        ]);
    }
}
