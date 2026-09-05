<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ordering;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Entitlement\UseCase\RequireEntitlement;
use App\Application\Ordering\Port\OrderingSwitchPort;
use App\Domain\Authorization\Permission;
use App\Domain\Entitlement\Entitlement;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "BU ŞUBE ŞU AN SİPARİŞ ALIYOR MU?" — okuma ucu (`docs/115` S6, Y1/Y3).
 *
 * OKUMAK İÇİN `order.view` YETER, çevirmek için `order.settings` gerekir.
 * Ayrım gerçek bir ihtiyaçtan geliyor: garson, bekleyen kuyruk boşken
 * "hiç sipariş yok" ile "sipariş alma kapalı" arasındaki farkı görmeli.
 * İkisini ayırt edemeyen bir ekran, kapalı bir hizmeti sessiz bir akşam
 * gibi gösterirdi — ve kimse şalteri açmayı akıl etmezdi.
 *
 * ═══ Y3: ÜÇÜNCÜ BİR CEVAP DAHA VAR ═══
 *
 * "Kapalı" ile "planda yok" aynı şey değildir ve bu uç ikisini AYRI
 * bildirir. Tek bir birleşik değer dönseydi, planı sipariş almayı
 * içermeyen bir sahibin ekranı yalnız kapalı bir şalter gösterir, sebebini
 * söyleyemezdi — sahip onu açmayı deneyip ret alarak öğrenirdi. Kuyruk ve
 * mutfak ekranları da boş listeyi "bugün sipariş yok" diye anlatırdı; oysa
 * sipariş gelmiyor değil, GELEMİYOR.
 *
 * HAK CANLI OKUNUR, yayına donmuş hak burada kullanılmaz. Bu ekran sahibin
 * BUGÜNKÜ planını gösterir; misafirin gördüğü yayın ise `docs/114` §3 Dalga
 * 6 uyarınca basıldığı günün hakkını taşımaya devam eder. İkisi bilerek
 * farklı sorular: biri "ne satın aldım", öbürü "masadaki kâğıt ne diyor".
 */
final class ShowOrderingSwitchController extends Controller
{
    public function __construct(
        private readonly OrderingSwitchPort $orderingSwitch,
        private readonly AuthorizationPort $authorization,
        private readonly RequireEntitlement $requireEntitlement,
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
            /*
                ŞALTERİN HÂLİNDEN AYRI BİR ALAN. Sahip planını düşürdüğünde
                `accepts_orders` sütunu AÇIK kalır ve bilerek kalır: sunucunun
                sahibin ayarını arkasından değiştirmesi, geri geldiğinde neyi
                kaybettiğini bilmemesi demektir. Ekran o zaman "açık ama
                çalışmıyor" gerçeğini söyleyebilsin diye iki değer de gider.
            */
            'planIncludesOrdering' => $this->requireEntitlement->allows(
                $workspace,
                Entitlement::OrderingBasic,
            ),
            /*
                HAKKIN ADI HER ZAMAN GİDER, yalnız eksikken değil.

                Misafir tarafındaki 402 gövdesi (`StoreGuestOrderController`)
                aynı alanı taşıyor; iki taraf aynı kelimeyi kullanmazsa,
                sahibin panelde okuduğu kısıt ile misafirin ekranındaki ret
                bir gün farklı iki şeyi anlatmaya başlar.
            */
            'entitlement' => Entitlement::OrderingBasic->value,
        ]);
    }
}
