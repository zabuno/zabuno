<?php

declare(strict_types=1);

namespace App\Http\Controllers\Publication;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Publication\Port\PublicationSchedulePort;
use App\Application\Publication\UseCase\BuildScheduleOptions;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Planla" panelinin okunan yüzü: kurulu bir plan var mı, ve hangi saatler
 * önerilebilir.
 *
 * Saatler SUNUCUDA hesaplanır (`BuildScheduleOptions`). Ekran yalnız dönen
 * ANI okunabilir saate çevirir; hesap yapmaz.
 */
final class ShowPublicationScheduleController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly PublicationSchedulePort $schedules,
    ) {}

    public function __invoke(Request $request, int $workspace, int $menu): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $pending = $this->schedules->pendingForMenu($workspace, $menu);

        return response()->json([
            'timeZone' => BuildScheduleOptions::TIME_ZONE,
            'pending' => $pending === null ? null : [
                'id' => $pending->id,
                'scheduledFor' => $pending->scheduledFor,
                'state' => $pending->state,
            ],
            'options' => BuildScheduleOptions::forNow(now()),
        ], 200);
    }
}
