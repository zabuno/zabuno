<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Billing\UseCase\ManagePlanCatalog;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class ActivateManagedPlanController extends Controller
{
    public function __construct(
        private readonly ManagePlanCatalog $plans,
    ) {}

    public function __invoke(int $plan): JsonResponse
    {
        $activated = $this->plans->activate($plan);

        if ($activated === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json($activated->toArray());
    }
}
