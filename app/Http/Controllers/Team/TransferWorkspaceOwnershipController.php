<?php

declare(strict_types=1);

namespace App\Http\Controllers\Team;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Team\UseCase\TransferWorkspaceOwnership;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class TransferWorkspaceOwnershipController extends Controller
{
    public function __construct(
        private readonly TransferWorkspaceOwnership $transferWorkspaceOwnership,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $member): JsonResponse|Response
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceManage, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->transferWorkspaceOwnership->handle($workspace, $userId, $member)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->noContent();
    }
}
