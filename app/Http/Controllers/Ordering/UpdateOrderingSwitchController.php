<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ordering;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Ordering\Port\OrderingSwitchPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ordering\UpdateOrderingSwitchRequest;
use Illuminate\Http\JsonResponse;

/**
 * SİPARİŞ ALMAYI AÇ/KAPAT — `docs/115` S6, Y1 (FF-179).
 *
 * Göç bu sütunu VARSAYILAN KAPALI yazdı; bu uç o kararı panele taşır.
 * Sipariş alma, panelde birinin BAKMASINI gerektiren tek yetenektir:
 * kendiliğinden açılsaydı, güncelledikten sonra hiçbir şey yapmayan bir
 * restoranın mutfağına sessizce iş düşerdi.
 *
 * ŞUBE UCU (`PUT .../brand/locations/{location}`) İLE BİRLEŞTİRİLMEDİ ve
 * bu bilinçli: o uç `workspace.manage` ister, yani Yönetici de taşır.
 * Şalteri oraya koymak, tarifede yalnız Sahip'te olan bir kararı sessizce
 * Yöneticiye açmak olurdu — ve kimse bunu bir alan eklenirken fark etmezdi.
 */
final class UpdateOrderingSwitchController extends Controller
{
    public function __construct(
        private readonly OrderingSwitchPort $orderingSwitch,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(
        UpdateOrderingSwitchRequest $request,
        int $workspace,
        int $location,
    ): JsonResponse {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::OrderView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::OrderSettings, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $acceptsOrders = (bool) $request->validated('acceptsOrders');

        // `false` "böyle bir şube yok" demektir: kapsam koşulu portun
        // sorgusunun içinde durur, burada tekrarlanmaz.
        if (! $this->orderingSwitch->setAcceptsOrders($workspace, $location, $acceptsOrders)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json([
            'locationId' => $location,
            'acceptsOrders' => $acceptsOrders,
            'canManage' => true,
        ]);
    }
}
