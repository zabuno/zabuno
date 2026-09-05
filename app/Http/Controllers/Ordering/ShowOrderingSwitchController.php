<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ordering;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Ordering\Port\OrderingSwitchPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "BU ŞUBE ŞU AN SİPARİŞ ALIYOR MU?" — okuma ucu (`docs/115` S6, Y1).
 *
 * OKUMAK İÇİN `order.view` YETER, çevirmek için `order.settings` gerekir.
 * Ayrım gerçek bir ihtiyaçtan geliyor: garson, bekleyen kuyruk boşken
 * "hiç sipariş yok" ile "sipariş alma kapalı" arasındaki farkı görmeli.
 * İkisini ayırt edemeyen bir ekran, kapalı bir hizmeti sessiz bir akşam
 * gibi gösterirdi — ve kimse şalteri açmayı akıl etmezdi.
 */
final class ShowOrderingSwitchController extends Controller
{
    public function __construct(
        private readonly OrderingSwitchPort $orderingSwitch,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $location): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::OrderView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json([
            'locationId' => $location,
            'acceptsOrders' => $this->orderingSwitch->acceptsOrders($workspace, $location),
            /*
                Ekran, şalteri ÇEVİREBİLECEK mi bilmeli ki yapılamayan bir işi
                çizmesin (`docs/59`). Sunucunun kararının yerine geçmez — uç
                yetkiyi kendisi doğrular; buradaki iş yalnız yöneticiye
                basacağında 403 alacağı bir düğme göstermemektir.
            */
            'canManage' => $this->authorization->can($userId, Permission::OrderSettings, $workspace),
        ]);
    }
}
