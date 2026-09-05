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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ürünü menüden çıkarır — `docs/73` (P0-01).
 *
 * Yayınlanmış sürüm ETKİLENMEZ: yayın bir anlık görüntüdür ve JSON olarak
 * saklanır. Bugün silinen bir ürün, dün yayınlanmış menüde durmaya devam
 * eder — basılı QR'ı tarayan misafir bugünün taslağını değil, o günün
 * gerçeğini görür.
 */
final class DeleteMenuItemController extends Controller
{
    public function __construct(
        private readonly MenuCatalogRepositoryPort $menuCatalog,
        private readonly MenuCatalogApiContextPort $context,
        private readonly AuthorizationPort $authorization,
        private readonly MenuAuditPort $audit,
    ) {}

    public function __invoke(Request $request, int $workspace, int $menuItem): JsonResponse
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

        $menuItemContext = $this->context->menuItemContext($menuItem);

        if ($menuItemContext === null || $menuItemContext->workspaceId !== $workspace) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        try {
            $this->menuCatalog->deleteMenuItem($workspace, $menuItem);
        } catch (MenuCatalogTenantMismatchException) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        /*
            DENETİM İZİ (FF-154) — kaydın EN DEĞERLİ olduğu an.

            Satır artık yok; onu sorabileceğimiz hiçbir yer kalmadı. Bu
            yüzden hem adı hem son fiyatı kaydın İÇİNE kopyalanır ve konu
            yabancı anahtarla bağlanmaz: bir `cascade`, tam da sahibin
            "Mercimek Çorbası nereye gitti?" diye sorduğu satırı silerdi.
        */
        $this->audit->record(MenuAuditEntry::forItem(
            $workspace,
            $menuItemContext->menuId,
            $menuItem,
            $menuItemContext->productName,
            MenuAuditAction::ItemRemoved,
            MenuAuditEntry::price($menuItemContext->priceMinorAmount, $menuItemContext->currencyCode),
            null,
            $userId,
        ));

        return response()->json(['id' => $menuItem, 'deleted' => true]);
    }
}
