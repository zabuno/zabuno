<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Billing\Exception\WorkspaceNotFoundException;
use App\Application\Billing\UseCase\ManageSubscriptions;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class ShowManagedSubscriptionController extends Controller
{
    public function __construct(
        private readonly ManageSubscriptions $subscriptions,
    ) {}

    public function __invoke(int $workspace): JsonResponse
    {
        try {
            $summary = $this->subscriptions->current($workspace);
        } catch (WorkspaceNotFoundException) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json($summary->toArray());
    }
}
