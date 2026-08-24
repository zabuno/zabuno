<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Billing\UseCase\ListPlanCatalog;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListPlansController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly ListPlanCatalog $plans,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::BillingView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $plans = array_map(
            static fn ($plan): array => $plan->toArray(),
            $this->plans->handle(),
        );

        return response()->json($plans);
    }
}
