<?php

declare(strict_types=1);

namespace App\Http\Controllers\QrDestination;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Entitlement\UseCase\RequireEntitlement;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Application\QrDestination\Dto\QrCodeRecord;
use App\Application\QrDestination\Port\QrCodeRepositoryPort;
use App\Application\QrDestination\Port\QrScanCountPort;
use App\Domain\Authorization\Permission;
use App\Domain\Entitlement\Entitlement;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListQrCodesController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly MenuCatalogApiContextPort $context,
        private readonly QrCodeRepositoryPort $qrCodes,
        private readonly QrScanCountPort $scanCounts,
        private readonly RequireEntitlement $requireEntitlement,
    ) {}

    public function __invoke(Request $request, int $workspace, int $location): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::QrView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ($this->context->locationWorkspaceId($location) !== $workspace) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        /*
            TARAMA SAYISI ÖLÇÜMDÜR, ve ölçüm ücretlidir (CORE-04) — panel v3
            (`docs/109` §6.7).

            Kaynağın masa kartlarında tarama sayısı yazıyor ve bu, ekranın tek
            teşhis aracı: kırk masalı bir restoranda "Masa 17 hiç okutulmamış"
            bilgisini başka hiçbir yerden okuyamazsınız. Ama aynı veri
            `ShowAnalyticsSummaryController`'da 402 ile korunuyor; burada
            koşulsuz vermek, ödeme duvarını arka kapıdan açmak olurdu.

            Yetenek yoksa alan `null`'dır, `0` DEĞİL: sıfır "kod hiç
            taranmadı" der ve bu bir YALAN olurdu — bilmediğimiz şeyi
            bilmediğimizi söylüyoruz. Ekran iki hâli ayrı çizer.

            Yetki de ayrıca aranır: kodu görebilen herkes ölçümü göremez
            (`analytics.view`); mutfak rolü kartı basar, cirosal bilgiyi
            görmez.
        */
        $mayReadCounts = $this->authorization->can($userId, Permission::AnalyticsView, $workspace)
            && $this->requireEntitlement->allows($workspace, Entitlement::AnalyticsReporting);

        $scanCounts = $mayReadCounts ? $this->scanCounts->countsForLocation($workspace, $location) : [];

        $items = array_map(
            static fn (QrCodeRecord $record): array => [
                'id' => $record->id,
                'workspaceId' => $record->workspaceId,
                'locationId' => $record->locationId,
                'menuId' => $record->menuId,
                'token' => $record->token,
                'resolverUrl' => url("/q/{$record->token}"),
                'destinationType' => $record->destinationType,
                'state' => $record->state,
                // FF-109: kodun insan adı. `null` da bir cevaptır — masaya
                // bağlı olmayan kod için ad uydurulmaz.
                'tableName' => $record->tableName,
                'areaLabel' => $record->areaLabel,
                // Süzgeç KİMLİKLE çalışır: iki alan aynı adı taşıyabilir.
                'areaId' => $record->areaId,
                // Hiç taranmamış kod tabloda satır bırakmaz; o yokluk `0`
                // demektir. `null` yalnız ölçümün bize kapalı olduğu hâldir.
                'scanCount' => $mayReadCounts ? ($scanCounts[$record->id] ?? 0) : null,
            ],
            $this->qrCodes->listForLocation($workspace, $location),
        );

        return response()->json($items, 200);
    }
}
