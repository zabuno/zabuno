<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenancy;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Tenancy\Profile\UseCase\GetLocation;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowLocationController extends Controller
{
    public function __construct(
        private readonly GetLocation $getLocation,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $location): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $result = $this->getLocation->handle($workspace, $location);

        if ($result === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json($result->toArray());
    }
}
