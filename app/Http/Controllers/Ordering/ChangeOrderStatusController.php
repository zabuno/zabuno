<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ordering;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Ordering\Exception\InvalidOrderTransitionException;
use App\Application\Ordering\Exception\OrderNotFoundException;
use App\Application\Ordering\Port\OrderRepositoryPort;
use App\Application\Ordering\UseCase\ChangeOrderStatus;
use App\Domain\Authorization\Permission;
use App\Domain\Ordering\OrderActor;
use App\Domain\Ordering\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ordering\ChangeOrderStatusRequest;
use Illuminate\Http\JsonResponse;

/**
 * PANELDEN GELEN TEK DURUM DEĞİŞİKLİĞİ UCU — `docs/115` S4/S5 (FF-179).
 *
 * Onay, ret, "hazırlanıyor", "hazır" ve "teslim edildi" aynı kapıdan geçer.
 * Beş ayrı uç yazmak ilk bakışta daha okunur görünüyordu; ikinci bakışta
 * geçiş kuralının beş kopyası demekti ve biri eskidiğinde bunu ancak yanlış
 * bir sipariş mutfağa düştüğünde öğrenirdik. Kural `OrderStatus`'ta, yazma
 * `ChangeOrderStatus`'ta; burada yalnız KİMİN ne isteyebileceği durur.
 */
final class ChangeOrderStatusController extends Controller
{
    /**
     * HEDEF DURUM → GEREKEN İZİN. Bu haritanın kendisi bir ürün kararıdır.
     *
     * `confirmed`/`rejected` servis kararıdır: masada kimin oturduğunu gören
     * kişi verir. `preparing`/`ready` ocağın kendi cümlesidir. `delivered`
     * yine SERVİStir — tabağı masaya götüren kişi bilir; mutfağa verilseydi
     * ocaktan çıkan her tabak masaya gitmiş sayılırdı.
     *
     * @var array<string, Permission>
     */
    private const PERMISSION_FOR_TARGET = [
        'confirmed' => Permission::OrderConfirm,
        'rejected' => Permission::OrderConfirm,
        'preparing' => Permission::OrderKitchen,
        'ready' => Permission::OrderKitchen,
        'delivered' => Permission::OrderConfirm,
    ];

    public function __construct(
        private readonly ChangeOrderStatus $changeOrderStatus,
        private readonly OrderRepositoryPort $orders,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(
        ChangeOrderStatusRequest $request,
        int $workspace,
        int $location,
        int $order,
    ): JsonResponse {
        $userId = (int) $request->user()->getKey();

        /*
            GÖRME İZNİ YOKSA YÜZEY YOKTUR — 404, 403 değil.

            403 "burada bir şey var ama sana kapalı" der ve bu bir sayım
            kanalıdır: Editör siparişin var olduğunu bile öğrenmemeli
            (`docs/115` §4).
        */
        if (! $this->authorization->can($userId, Permission::OrderView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $target = OrderStatus::from((string) $request->validated('status'));
        $needed = self::PERMISSION_FOR_TARGET[$target->value];

        if (! $this->authorization->can($userId, $needed, $workspace)) {
            // Buradaki 403 dürüsttür: kullanıcı yüzeyi görüyor, yalnız BU
            // hareketi yapamıyor. Aşçı kuyruğu görür ama onaylayamaz.
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $reason = $request->validated('reason');

        try {
            $this->changeOrderStatus->handle(
                $workspace,
                $location,
                $order,
                $target,
                OrderActor::Staff,
                is_string($reason) ? $reason : null,
            );
        } catch (OrderNotFoundException) {
            return response()->json(['message' => 'Not Found.'], 404);
        } catch (InvalidOrderTransitionException $e) {
            /*
                409 ÇATIŞMA, 422 DEĞİL (`docs/115` G5).

                İstek geçerliydi; ortada bozulmuş bir şey de yok. Olan şey,
                işin başkası tarafından çoktan alınmış olması. Yanıt siparişin
                O ANKİ durumunu taşır ki ekran listeyi tazelemeden doğru
                cümleyi kurabilsin — "zaten onaylandı", "artık hazırlanıyor".
            */
            return response()->json([
                'message' => $e->getMessage(),
                'status' => $e->current->value,
            ], 409);
        }

        $current = $this->orders->statusInScope($workspace, $location, $order);

        return response()->json([
            'id' => $order,
            'status' => ($current ?? $target)->value,
        ]);
    }
}
