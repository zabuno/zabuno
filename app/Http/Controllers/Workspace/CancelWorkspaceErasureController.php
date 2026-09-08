<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspace;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\DataRights\UseCase\CancelWorkspaceErasure;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Vazgeçtim" — FF-226.
 *
 * İptal için ONAY METNİ İSTENMEZ ve bu bilinçli: geri alınabilir pencerenin
 * tek işi kolay geri alınmasıdır. Vazgeçmeyi zorlaştırmak, silmeyi
 * kolaylaştırmakla aynı şeydir.
 */
final class CancelWorkspaceErasureController extends Controller
{
    public function __construct(
        private readonly CancelWorkspaceErasure $cancel,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $dataRequest): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::WorkspaceDataErase, $workspace)) {
            return response()->json([
                'message' => 'Forbidden.',
                'requiredPermission' => Permission::WorkspaceDataErase->value,
            ], 403);
        }

        if (! $this->cancel->handle($workspace, $dataRequest, $userId)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json(['cancelled' => true]);
    }
}
