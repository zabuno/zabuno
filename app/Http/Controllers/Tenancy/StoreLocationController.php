<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenancy;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Tenancy\Profile\UseCase\CreateLocation;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\StoreLocationRequest;
use Illuminate\Http\JsonResponse;

final class StoreLocationController extends Controller
{
    public function __construct(
        private readonly CreateLocation $createLocation,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(StoreLocationRequest $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::WorkspaceManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $location = $this->createLocation->handle($workspace, $request->validated());

        if ($location === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json($location->toArray(), 201);
    }
}
