<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenancy;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Tenancy\Profile\UseCase\UpdateLocation;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\UpdateLocationRequest;
use Illuminate\Http\JsonResponse;

final class UpdateLocationController extends Controller
{
    public function __construct(
        private readonly UpdateLocation $updateLocation,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(UpdateLocationRequest $request, int $workspace, int $location): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::WorkspaceManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $result = $this->updateLocation->handle($workspace, $location, $request->validated());

        if ($result === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json($result->toArray());
    }
}
