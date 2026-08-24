<?php

declare(strict_types=1);

namespace App\Http\Controllers\Analytics;

use App\Application\Analytics\Port\AnalyticsRepositoryPort;
use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class ShowAnalyticsSummaryController extends Controller
{
    private const ALLOWED_RANGES = ['today', '7d', '30d'];

    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly MenuCatalogApiContextPort $context,
        private readonly AnalyticsRepositoryPort $analytics,
    ) {}

    public function __invoke(Request $request, int $workspace, int $location): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::AnalyticsView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ($this->context->locationWorkspaceId($location) !== $workspace) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $range = (string) $request->query('range', 'today');

        if (! in_array($range, self::ALLOWED_RANGES, true)) {
            return response()->json(['message' => 'Invalid range.'], 422);
        }

        $summary = $this->analytics->summarize($workspace, $location, $range, Carbon::now());

        return response()->json($summary->toArray());
    }
}
