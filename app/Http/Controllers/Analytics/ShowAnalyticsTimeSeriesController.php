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

/**
 * "Bu 7 günde ne oldu?" — `docs/109` §1 (Insights), §6.5.
 *
 * `summary` yalnız ARALIK TOPLAMI veriyor ve o toplam bir haftanın şeklini
 * gizliyor. Sahibin cumartesi akşamı kasanın başında sorduğu şey "214" değil:
 * bu hafta geçen haftadan iyi mi, hangi gün çöktü, öğle mi akşam mı yoğun,
 * Kadıköy mü Beşiktaş mı çekiyor? Dördü de bugüne kadar üretilemiyordu.
 *
 * AYRI BİR UÇ olmasının sebebi kapsam: `summary` bugün panonun sayaçlarını
 * besliyor ve onun yanıtına yeni alanlar eklemek, o yanıtı okuyan her
 * istemciyi etkilerdi. Yeni yetenek yeni bir adresten konuşur.
 */
final class ShowAnalyticsTimeSeriesController extends Controller
{
    /** `summary` ile AYNI liste: iki uç aynı pencerelerden konuşmalı. */
    private const ALLOWED_RANGES = ['today', '7d', '30d'];

    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly MenuCatalogApiContextPort $context,
        private readonly AnalyticsRepositoryPort $analytics,
        private readonly RequireEntitlement $requireEntitlement,
    ) {}

    public function __invoke(Request $request, int $workspace, ?int $location = null): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::AnalyticsView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        /*
            Şube verilmişse KİRACIYA AİT OLDUĞU doğrulanır ve uymadığında 404
            döner, 403 değil: 403 o şubenin VAR OLDUĞUNU söylerdi.
        */
        if ($location !== null && $this->context->locationWorkspaceId($location) !== $workspace) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        /*
            CORE-04: raporlama plana bağlı (owner kararı, 2026-08-26). 402
            kullanılır, 403 değil — kullanıcı yetkisiz DEĞİL, planı bu
            yeteneği içermiyor ve çıkış yolu plan yükseltmesidir.
        */
        try {
            $this->requireEntitlement->handle($workspace, Entitlement::AnalyticsReporting);
        } catch (EntitlementDeniedException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'entitlement' => $e->entitlement->value,
            ], 402);
        }

        $range = (string) $request->query('range', '7d');

        if (! in_array($range, self::ALLOWED_RANGES, true)) {
            return response()->json(['message' => 'Invalid range.'], 422);
        }

        return response()->json(
            $this->analytics->timeSeries($workspace, $location, $range, Carbon::now())->toArray(),
        );
    }
}
