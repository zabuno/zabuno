<?php

declare(strict_types=1);

namespace App\Http\Controllers\Analytics;

use App\Application\Analytics\Port\AnalyticsRepositoryPort;
use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Entitlement\Exception\EntitlementDeniedException;
use App\Application\Entitlement\UseCase\RequireEntitlement;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Domain\Authorization\Permission;
use App\Domain\Entitlement\Entitlement;
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
        private readonly RequireEntitlement $requireEntitlement,
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

        // CORE-04: bu yetenek plana bağlıdır (owner kararı, 2026-08-26).
        // 402 kullanılır, 403 değil: kullanıcı yetkisiz DEĞİL, planı bu
        // yeteneği içermiyor. Çıkış yolu farklıdır — biri erişim talebi,
        // diğeri plan yükseltmesidir.
        try {
            $this->requireEntitlement->handle($workspace, Entitlement::AnalyticsReporting);
        } catch (EntitlementDeniedException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'entitlement' => $e->entitlement->value,
            ], 402);
        }

        $range = (string) $request->query('range', 'today');

        if (! in_array($range, self::ALLOWED_RANGES, true)) {
            return response()->json(['message' => 'Invalid range.'], 422);
        }

        $summary = $this->analytics->summarize($workspace, $location, $range, Carbon::now());

        return response()->json($summary->toArray());
    }
}
