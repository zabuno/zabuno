<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Billing\UseCase\ManagePlanCatalog;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class ListManagedPlansController extends Controller
{
    public function __construct(
        private readonly ManagePlanCatalog $plans,
    ) {}

    public function __invoke(): JsonResponse
    {
        $plans = array_map(
            static fn ($plan): array => $plan->toArray(),
            $this->plans->listAllVersions(),
        );

        return response()->json($plans);
    }
}
