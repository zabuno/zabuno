<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenancy;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Tenancy\Profile\UseCase\GetBrand;
use App\Application\Tenancy\Profile\UseCase\ListLocations;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListLocationsController extends Controller
{
    public function __construct(
        private readonly GetBrand $getBrand,
        private readonly ListLocations $listLocations,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $brand = $this->getBrand->handle($workspace);

        if ($brand === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $locations = $this->listLocations->handle($workspace, $brand->id);

        return response()->json(array_map(
            static fn ($location) => $location->toArray(),
            $locations,
        ));
    }
}
