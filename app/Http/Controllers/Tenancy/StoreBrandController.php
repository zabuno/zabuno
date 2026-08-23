<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenancy;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Tenancy\Profile\UseCase\CreateBrand;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\StoreBrandRequest;
use Illuminate\Http\JsonResponse;

final class StoreBrandController extends Controller
{
    public function __construct(
        private readonly CreateBrand $createBrand,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(StoreBrandRequest $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::WorkspaceManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $brand = $this->createBrand->handle($workspace, $request->validated());

        return response()->json($brand->toArray(), 201);
    }
}
