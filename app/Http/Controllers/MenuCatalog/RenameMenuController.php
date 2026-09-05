<?php

declare(strict_types=1);

namespace App\Http\Controllers\MenuCatalog;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Application\MenuCatalog\Dto\MenuAuditEntry;
use App\Application\MenuCatalog\Exception\MenuCatalogTenantMismatchException;
use App\Application\MenuCatalog\Port\MenuAuditPort;
use App\Application\MenuCatalog\Port\MenuSchedulePort;
use App\Domain\Authorization\Permission;
use App\Domain\MenuCatalog\MenuAuditAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Menünün adını düzeltir.
 *
 * Çoklu menüden önce menünün adını değiştirmenin hiçbir yolu yoktu; tek
 * menü olduğu için adı da pek önemli değildi. Haplarla birlikte ad EKRANDA
 * OKUNAN ŞEY oldu: "Kahvltı" yazan bir hapı düzeltmenin yolu olmalı.
 */
final class RenameMenuController extends Controller
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

        $validated = $request->validate(['name' => ['required', 'string', 'max:120']]);

        // Eski ad yalnız DEĞİŞMEDEN ÖNCE okunabilir (FF-154). Kiracı
        // sınırını yine yazma kapısı zorlar; buradaki okuma bir yetki
        // kontrolü değil, denetim izinin "öncesi" alanıdır.
        $before = $this->context->menuContext($menu);

        try {
            $entry = $this->schedule->rename($workspace, $menu, (string) $validated['name']);
        } catch (MenuCatalogTenantMismatchException) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ($before !== null && $before->name !== $entry->name) {
            $this->audit->record(MenuAuditEntry::forMenu(
                $workspace,
                $menu,
                $entry->name,
                MenuAuditAction::MenuRenamed,
                $before->name,
                $entry->name,
                $userId,
            ));
        }

        return response()->json([
            'id' => $entry->id,
            'name' => $entry->name,
            'state' => $entry->state,
            'sortOrder' => $entry->sortOrder,
            'startsAt' => $entry->startsAt,
            'endsAt' => $entry->endsAt,
            'windows' => $entry->windows,
            'isServingNow' => $entry->isServingNow,
            'isAddressAnchor' => $entry->isAddressAnchor,
        ]);
    }
}
