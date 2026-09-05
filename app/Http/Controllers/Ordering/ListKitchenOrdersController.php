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
 * MUTFAK MONİTÖRÜ BESLEMESİ — `docs/115` S5, K1 (FF-179).
 *
 * Sahibin cümlesi: "Restoran Admin panelden onaylarsa sipariş mutfak
 * tarafındaki monitöre düşer." Bu ucun tek işi o "ONAYLARSA" kelimesini
 * tutmaktır: bekleyen sipariş buradan HİÇ dönmez.
 *
 * Ayrı bir uç, çünkü ayrı bir izin: `order.kitchen`. Kuyruk ucuna bir
 * `?status=` parametresi eklemek daha az dosya olurdu ve yetkiyi bir sorgu
 * dizesine bağlardı — mutfağın göreceği şey bir parametre değil, bu ürünün
 * kemik kuralıdır.
 */
final class ListKitchenOrdersController extends Controller
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

        if (! $this->authorization->can($userId, Permission::OrderKitchen, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $orders = $this->orders->kitchenBoard($workspace, $location, EloquentOrderQuery::BOARD_LIMIT);

        return response()->json([
            'data' => array_map(
                static fn (OrderSummary $order): array => $order->toArray(),
                $orders,
            ),
            // Ekran donduğunda dolu bir ekranla aynı görünür; son güncelleme
            // anı bu yüzden yazılır (`docs/115` §6).
            'serverTime' => (new DateTimeImmutable('now'))->format(DateTimeImmutable::ATOM),
        ]);
    }
}
