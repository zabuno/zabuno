<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Billing\Exception\PlanVersionConflictException;
use App\Application\Billing\UseCase\ManagePlanCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreManagedPlanRequest;
use Illuminate\Http\JsonResponse;

final class StoreManagedPlanController extends Controller
{
    public function __construct(
        private readonly ManagePlanCatalog $plans,
    ) {}

    public function __invoke(StoreManagedPlanRequest $request): JsonResponse
    {
        $amountMinor = $request->validated('amount_minor');
        $currency = $request->validated('currency');

        try {
            $plan = $this->plans->create(
                (string) $request->validated('name'),
                (string) $request->validated('code'),
                (int) $request->validated('version'),
                array_values((array) $request->validated('entitlements')),
                $amountMinor === null ? null : (int) $amountMinor,
                $currency === null ? null : (string) $currency,
                (int) $request->validated('sort_order'),
            );
        } catch (PlanVersionConflictException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($plan->toArray(), 201);
    }
}
