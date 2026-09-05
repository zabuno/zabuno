<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ordering;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Entitlement\UseCase\RequireEntitlement;
use App\Application\Ordering\Port\OrderingSwitchPort;
use App\Domain\Authorization\Permission;
use App\Domain\Entitlement\Entitlement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ordering\UpdateOrderingSwitchRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * SİPARİŞ ALMAYI AÇ/KAPAT — `docs/115` S6, Y1/Y3 (FF-179/184).
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
 *
 * ═══ Y3: ŞALTER, PLANIN VERMEDİĞİ SÖZÜ VEREMEZ ═══
 *
 * Ölçülen kusur şuydu: bu uç şalteri açıyordu, misafirin siparişi ise
 * `ordering.basic` yoksa 402 ile reddediliyordu. Sahip hizmeti açtığını
 * sanıyordu; mutfağa hiçbir şey düşmüyordu ve bunu ancak bir misafir
 * denediğinde — o da sessizce — öğreniyordu.
 *
 * KAPI EKRANDA DEĞİL BURADA. Yalnız ekranı kilitlemek kuralı bir cümleye
 * indirir: uç açık kaldığı sürece kural, isteği elle gönderen ilk kişide
 * biter. Ekranın işi bu reddi kullanıcıya yaşatmamak, bu ucun işi ise
 * kuralın kendisi olmaktır.
 *
 * KAPATMAK HER ZAMAN SERBEST. Hak düştüğünde şalter açık kalmış olabilir;
 * sahibin kendi hizmetini kapatamadığı bir ekran, planı düşmüş bir restoranı
 * kilitlemek olurdu. Gate yalnız AÇMAYA bakar.
 */
final class UpdateOrderingSwitchController extends Controller
{
    public function __construct(
        private readonly OrderingSwitchPort $orderingSwitch,
        private readonly AuthorizationPort $authorization,
        private readonly RequireEntitlement $requireEntitlement,
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
        $planIncludesOrdering = $this->requireEntitlement->allows($workspace, Entitlement::OrderingBasic);

        if ($acceptsOrders && ! $planIncludesOrdering) {
            /*
                VARLIK, PLANDAN ÖNCE SORULUR.

                Kiracıya ait olmayan bir şube için "senin planında sipariş
                alma yok" demek, o şubenin varlığını kabul etmek ve yanlış
                bir çıkış yolu göstermek olurdu: sahip planını yükseltir,
                sonra yine hiçbir şey olmaz.
            */
            if (! $this->orderingSwitch->belongsToWorkspace($workspace, $location)) {
                return response()->json(['message' => 'Not Found.'], 404);
            }

            /*
                402 VE HAKKIN ADI — misafir tarafındaki kapının dilinin
                aynısı (`StoreGuestOrderController`). 403 değil: kullanıcı
                yetkisiz DEĞİL, planı bu hizmeti içermiyor. Çıkış yolları
                farklıdır — biri erişim talebi, öbürü plan değişikliği.
            */
            return response()->json([
                'message' => 'Ordering is not included in this plan.',
                'entitlement' => Entitlement::OrderingBasic->value,
            ], SymfonyResponse::HTTP_PAYMENT_REQUIRED);
        }

        // `false` "böyle bir şube yok" demektir: kapsam koşulu portun
        // sorgusunun içinde durur, burada tekrarlanmaz.
        if (! $this->orderingSwitch->setAcceptsOrders($workspace, $location, $acceptsOrders)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json([
            'locationId' => $location,
            'acceptsOrders' => $acceptsOrders,
            'canManage' => true,
            // Okuma ucuyla AYNI gövde: kapatma isteğinden sonra ekran yeniden
            // okuma yapmadan da "hak hâlâ eksik" cümlesini koruyabilsin.
            'planIncludesOrdering' => $planIncludesOrdering,
            'entitlement' => Entitlement::OrderingBasic->value,
        ]);
    }
}
