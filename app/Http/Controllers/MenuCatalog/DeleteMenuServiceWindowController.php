<?php

declare(strict_types=1);

namespace App\Http\Controllers\MenuCatalog;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Exception\MenuCatalogTenantMismatchException;
use App\Application\MenuCatalog\Port\MenuSchedulePort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MENÜYÜ KAPATIR — kaynağın "Ramazan (kapalı)" hapı.
 *
 * Menü silinmez, yalnız rotasyondan çıkar: Ramazan gelecek yıl geri
 * gelecek ve altmış ürünü yeniden yazmak kimsenin işine yaramaz.
 *
 * Bıraktığı saatler kendisinden önceki menüye geri döner; çemberde bir yay
 * silinince komşusu genişler ve delik açılmaz (`docs/109` §7.1).
 */
final class DeleteMenuServiceWindowController extends Controller
{
    public function __construct(
        private readonly MenuSchedulePort $schedule,
        private readonly AuthorizationPort $authorization,
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

        try {
            $this->schedule->clearServiceWindow($workspace, $menu);
        } catch (MenuCatalogTenantMismatchException) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json(['id' => $menu, 'state' => 'disabled']);
    }
}
