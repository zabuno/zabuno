<?php

declare(strict_types=1);

namespace App\Http\Controllers\MenuCatalog;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\MenuCatalog\UpdateMenuItemAllergensRequest;
use Illuminate\Http\JsonResponse;

final class UpdateMenuItemAllergensController extends Controller
{
    public function __construct(
        private readonly MenuCatalogRepositoryPort $menuCatalog,
        private readonly MenuCatalogApiContextPort $context,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(UpdateMenuItemAllergensRequest $request, int $workspace, int $menuItem): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        /*
            DAR İZİN — `menu.manage` DEĞİL (`docs/109` §6.4, Mutfak rolü).

            Alerjen bilgisini düzeltmek menüyü yeniden yazmak değildir: fiyat
            değişmez, ürün eklenmez, hiçbir şey silinmez. Burada geniş izni
            aramak, mutfaktaki insana alerjeni açmanın tek yolunu "bütün
            menünün fiyatlarını da aç" yapardı.

            Bu bir GEVŞETME değildir: `menu.allergens.manage` bugün
            Owner/Manager/Editor'ün de listesinde ve `Member` onu taşımıyor,
            yani dünkü davranış aynen duruyor.
        */
        if (! $this->authorization->can($userId, Permission::MenuAllergensManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $menuItemContext = $this->context->menuItemContext($menuItem);

        if ($menuItemContext === null || $menuItemContext->workspaceId !== $workspace) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        /** @var list<string> $allergens */
        $allergens = $request->validated('allergens');

        $resultingAllergens = $this->menuCatalog->replaceProductAllergens($workspace, $menuItemContext->productId, $allergens);

        return response()->json([
            'id' => $menuItem,
            'allergens' => $resultingAllergens,
        ]);
    }
}
