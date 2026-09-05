<?php

declare(strict_types=1);

namespace App\Http\Controllers\MenuCatalog;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Application\MenuCatalog\Dto\MenuScheduleEntry;
use App\Application\MenuCatalog\Port\MenuSchedulePort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MENÜ HAPLARININ VERİSİ — kanonik kaynak `panel.dc.html`, "Menüler"
 * ekranı: "Ana menü yayında · Kahvaltı 07–11 · Ramazan kapalı".
 *
 * Hapın taşıdığı her şey buradan gelir ve hiçbiri uydurulmaz: ad
 * `menus.name`, durum `menus.state`, saat ipucu ise şubenin geçiş
 * anlarından HESAPLANIR (`docs/109` §7.1).
 */
final class ListLocationMenusController extends Controller
{
    public function __construct(
        private readonly MenuSchedulePort $schedule,
        private readonly MenuCatalogApiContextPort $context,
        private readonly AuthorizationPort $authorization,
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

        return response()->json([
            'data' => array_map(
                static fn (MenuScheduleEntry $entry): array => [
                    'id' => $entry->id,
                    'name' => $entry->name,
                    'state' => $entry->state,
                    'sortOrder' => $entry->sortOrder,
                    'startsAt' => $entry->startsAt,
                    'endsAt' => $entry->endsAt,
                    'windows' => $entry->windows,
                    'isServingNow' => $entry->isServingNow,
                    'isAddressAnchor' => $entry->isAddressAnchor,
                ],
                $this->schedule->forLocation($workspace, $location),
            ),
        ]);
    }
}
