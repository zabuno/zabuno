<?php

declare(strict_types=1);

namespace App\Http\Controllers\MenuCatalog;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Application\MenuCatalog\Dto\MenuAuditEntry;
use App\Application\MenuCatalog\Port\MenuAuditPort;
use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Domain\MenuCatalog\MenuAuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\MenuCatalog\UpdateMenuItemAllergensRequest;
use Illuminate\Http\JsonResponse;

final class UpdateMenuItemAllergensController extends Controller
{
    public function __construct(
        private readonly MenuCatalogRepositoryPort $menuCatalog,
        private readonly MenuCatalogApiContextPort $context,
        private readonly AuthorizationPort $authorization,
        private readonly MenuAuditPort $audit,
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

        // Öncesi yalnız YAZMADAN ÖNCE okunabilir; sonrası artık yok.
        $previousAllergens = $this->context->allergensForProduct($menuItemContext->productId);

        $resultingAllergens = $this->menuCatalog->replaceProductAllergens($workspace, $menuItemContext->productId, $allergens);

        /*
            DENETİM İZİ (FF-154) — burada gerekçe YASAL.

            Bir üründen "fındık" işaretinin kaldırılması, alerjisi olan bir
            misafir için hayati bir bilgiyi yok eder. Böyle bir değişikliğin
            failsiz kalması, ürünün taşıyamayacağı bir sorumluluktur. Bu uç
            noktanın izni DAR (`menu.allergens.manage`, Mutfak rolü) — yani
            değişikliği yapan kişi çoğu zaman menüyü yöneten kişi değildir
            ve "kim" sorusu tam da bu yüzden sorulur.

            Değişmeyen küme yazılmaz; sıra da farkı belirlemez, çünkü iki
            taraf da alfabetik yazılır.
        */
        $before = MenuAuditEntry::allergens($previousAllergens);
        $after = MenuAuditEntry::allergens($resultingAllergens);

        if ($before !== $after) {
            $this->audit->record(MenuAuditEntry::forItem(
                $workspace,
                $menuItemContext->menuId,
                $menuItem,
                $menuItemContext->productName,
                MenuAuditAction::ItemAllergensChanged,
                $before,
                $after,
                $userId,
            ));
        }

        return response()->json([
            'id' => $menuItem,
            'allergens' => $resultingAllergens,
        ]);
    }
}
