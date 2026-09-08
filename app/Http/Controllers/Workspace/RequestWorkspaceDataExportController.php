<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspace;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\DataRights\UseCase\RequestWorkspaceExport;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Verimin bir kopyasını istiyorum" — FF-226.
 *
 * 202 döner, 200 değil: iş HENÜZ BİTMEDİ ve cevabın bunu söylemesi
 * gerekir. 200 dönseydi istemci arşivin hazır olduğunu sanır ve olmayan
 * bir bağlantıyı ararlardı.
 */
final class RequestWorkspaceDataExportController extends Controller
{
    public function __construct(
        private readonly RequestWorkspaceExport $export,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::WorkspaceDataExport, $workspace)) {
            return response()->json([
                'message' => 'Forbidden.',
                'requiredPermission' => Permission::WorkspaceDataExport->value,
            ], 403);
        }

        $created = $this->export->handle($workspace, $userId);

        return response()->json(['id' => $created->id, 'state' => $created->state->value], 202);
    }
}
