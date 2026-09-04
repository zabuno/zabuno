<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspace;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Workspace\Port\WorkspaceAuditTrailPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Denetim izi — Ayarlar'ın dördüncü sekmesi (FF-132).
 *
 * İki basamaklı kapı, medya izindeki kuralla AYNI ve sebebi de aynı:
 * çalışma alanını hiç göremeyen için kayıt HİÇ YOKTUR (404), çünkü 403
 * "böyle bir yer var ama sana kapalı" der ve bu da bir bilgidir. Üye olan
 * ama yönetme izni olmayan için 403 doğrudur: yerin varlığını zaten biliyor,
 * kapalı olan yalnız izin kendisi.
 */
final class ShowWorkspaceAuditTrailController extends Controller
{
    public function __construct(
        private readonly WorkspaceAuditTrailPort $trail,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::WorkspaceManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json(['data' => $this->trail->recent($workspace)]);
    }
}
