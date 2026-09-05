<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ordering;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Ordering\Dto\OrderSummary;
use App\Application\Ordering\Port\OrderQueryPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use App\Infrastructure\Ordering\Persistence\EloquentOrderQuery;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GARSON KUYRUĞU — `docs/115` S4, G1 (FF-179).
 *
 * Bu uç yoklamayla (polling) çağrılır ve bu bir eksiklik olarak YAZILIDIR
 * (`docs/115` §6): depoda WebSocket yok, kuyruk cron ile yürüyor, yani
 * "anında" bir kanal bugün yok. Ekran "anlık" demez; son güncelleme anını
 * yazar — mutfakta donmuş bir ekranla dolu bir ekran aynı görünür.
 *
 * Bu yüzden yanıt SUNUCUNUN ANINI da taşır. Ekranın kendi saati yanlış
 * olabilir ve mutfak duvarındaki bir ekranda genellikle yanlıştır; "dokuz
 * dakikadır bekliyor" cümlesi o saatten hesaplanırsa, ekran kendi hatasını
 * misafirin bekleme süresi diye gösterir.
 */
final class ListPendingOrdersController extends Controller
{
    public function __construct(
        private readonly OrderQueryPort $orders,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $location): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::OrderView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $orders = $this->orders->pending($workspace, $location, EloquentOrderQuery::BOARD_LIMIT);

        return response()->json([
            'data' => array_map(
                static fn (OrderSummary $order): array => $order->toArray(),
                $orders,
            ),
            'serverTime' => (new DateTimeImmutable('now'))->format(DateTimeImmutable::ATOM),
        ]);
    }
}
