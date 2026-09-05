<?php

declare(strict_types=1);

namespace App\Http\Controllers\MenuCatalog;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Exception\MenuCatalogTenantMismatchException;
use App\Application\MenuCatalog\Port\MenuSchedulePort;
use App\Domain\Authorization\Permission;
use App\Domain\MenuCatalog\ServiceDayTimeline;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * MENÜYE SAAT VERİR — "Kahvaltı 07:00–11:00".
 *
 * Sahibin kuralı: aralıklar çakışamaz ve boşluk bırakamaz (`docs/109`
 * §7.1). Bu yol o kuralı DOĞRULAMAZ, kuralın bozulamayacağı bir yazma
 * yapar: başlangıç anına bu menü, bitiş anına ise o anı ÖNCEDEN kaplayan
 * menü yazılır. "11:00'de bitsin" demek, "11:00'de eskiden ne varsa geri
 * gelsin" demektir.
 *
 * `startsAt === endsAt` TÜM GÜN demektir; şubenin varsayılan menüsü budur.
 * `22:00–02:00` özel bir durum değildir: iki geçiş yazılır, gün bir çember
 * olduğu için gerisi kendiliğinden doğrudur.
 */
final class UpdateMenuServiceWindowController extends Controller
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

        $validated = $request->validate([
            'startsAt' => ['required', 'string'],
            'endsAt' => ['required', 'string'],
        ]);

        try {
            $startMinute = ServiceDayTimeline::minuteFromClock((string) $validated['startsAt']);
            $endMinute = ServiceDayTimeline::minuteFromClock((string) $validated['endsAt']);
        } catch (InvalidArgumentException) {
            // Saat SS:DD yazılır. Yanlış biçim bir sunucu hatası değil,
            // sahibin düzeltebileceği bir giriştir.
            return response()->json(['message' => 'Saat SS:DD biçiminde yazılmalı (00:00–23:59).'], 422);
        }

        try {
            $this->schedule->setServiceWindow($workspace, $menu, $startMinute, $endMinute);
        } catch (MenuCatalogTenantMismatchException) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $locationId = $this->schedule->locationIdForMenu($menu);

        if ($locationId === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        foreach ($this->schedule->forLocation($workspace, $locationId) as $entry) {
            if ($entry->id !== $menu) {
                continue;
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

        return response()->json(['message' => 'Not Found.'], 404);
    }
}
