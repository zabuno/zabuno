<?php

declare(strict_types=1);

namespace App\Http\Controllers\MenuCatalog;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Application\MenuCatalog\Dto\MenuAuditEntry;
use App\Application\MenuCatalog\Exception\LastMenuForLocationException;
use App\Application\MenuCatalog\Exception\MenuCatalogTenantMismatchException;
use App\Application\MenuCatalog\Port\MenuAuditPort;
use App\Application\MenuCatalog\Port\MenuSchedulePort;
use App\Domain\Authorization\Permission;
use App\Domain\MenuCatalog\MenuAuditAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Menüyü siler.
 *
 * İKİ ŞEY BURADA KORUNUR:
 *
 * 1. Şubenin SON menüsü silinemez (409). Silinseydi karekodu okutan
 *    misafir boş bir sayfa görürdü ve restoranın yapabileceği bir şey
 *    olmazdı.
 * 2. Silinen menü şubenin genel adresini taşıyorsa adres hayatta kalan
 *    menüye taşınır ve karekod hedefleri oraya yönlenir — *"basılı kod
 *    hiç değişmez"* (`docs/109` §3).
 */
final class DeleteMenuController extends Controller
{
    public function __construct(
        private readonly MenuSchedulePort $schedule,
        private readonly AuthorizationPort $authorization,
        private readonly MenuCatalogApiContextPort $context,
        private readonly MenuAuditPort $audit,
    ) {}

    public function __invoke(Request $request, int $workspace, int $menu): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MenuManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Silinen menünün adı SONRADAN sorulamaz (FF-154).
        $before = $this->context->menuContext($menu);

        try {
            $this->schedule->delete($workspace, $menu);
        } catch (LastMenuForLocationException) {
            return response()->json([
                'message' => 'Bu şubenin tek menüsü silinemez; önce yeni bir menü ekleyin.',
            ], 409);
        } catch (MenuCatalogTenantMismatchException) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        /*
            DENETİM İZİ (FF-154). Menü silmek kategorileri, satırları ve
            ürün bağlarını da götürür; menü düzeyindeki en yıkıcı işlemdir
            ve bir failsiz kalamaz. İçerideki kategori/satırlar için ayrı
            kayıt yazılmaz — sorulacak soru "menüyü kim sildi"dir.
        */
        $this->audit->record(MenuAuditEntry::forMenu(
            $workspace,
            $menu,
            $before?->name,
            MenuAuditAction::MenuDeleted,
            $before?->name,
            null,
            $userId,
        ));

        return response()->json(['id' => $menu, 'deleted' => true]);
    }
}
