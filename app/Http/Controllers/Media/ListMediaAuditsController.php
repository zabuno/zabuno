<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MediaAuditPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Medya denetim izi — "bu fotoğrafı kim sildi?" (`docs/49` Faz 7 madde 4).
 *
 * İzi OKUMAK yönetme izni ister: kimin ne yaptığı, ekipteki herkesin
 * göreceği bir şey değildir. Görme izni olmayan için 404 döner, 403 değil —
 * 403 "böyle bir kayıt var ama sana kapalı" der ve bu da bir bilgidir.
 */
final class ListMediaAuditsController extends Controller
{
    public function __construct(
        private readonly MediaAuditPort $audits,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MediaManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json(['data' => $this->audits->recent($workspace)]);
    }
}
