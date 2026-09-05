<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ordering;

use App\Application\Analytics\UseCase\RecordAnalyticsEvent;
use App\Application\Entitlement\Port\EntitlementRepositoryPort;
use App\Application\Ordering\Exception\OrderLineRejectedException;
use App\Application\Ordering\Exception\OrderPersistenceFailedException;
use App\Application\Ordering\Port\OrderableMenuPort;
use App\Application\Ordering\Port\OrderingSwitchPort;
use App\Application\Ordering\Port\OrderRepositoryPort;
use App\Application\Ordering\UseCase\BuildOrderLines;
use App\Application\Publication\Port\PublicationRepositoryPort;
use App\Application\QrDestination\Port\QrCodeRepositoryPort;
use App\Domain\Analytics\AnalyticsEventType;
use App\Domain\Entitlement\Entitlement;
use App\Domain\Ordering\OrderStatus;
use App\Domain\QrDestination\QrToken;
use App\Http\Controllers\Controller;
use App\Http\Responses\GuestDeadEnd;
use App\Support\Analytics\VisitorKey;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * MİSAFİRİN SİPARİŞİ — `docs/115` S2.
 *
 * Bu ucun tuttuğu söz kısa: **misafir oturum açmaz, masasını da söylemez.**
 * Masa karekodun kendisinden okunur. Sahibin tarifi buydu ve teknik sonucu
 * şudur: bu akışta "yanlış masaya sipariş yazmak" diye bir hata sınıfı
 * yoktur, çünkü masa hiçbir zaman istemcinin elinden geçmez.
 *
 * ═══ RET, BOŞ EKRAN DEĞİLDİR ═══
 *
 * Reddin dört ayrı sebebi ve dört ayrı durum kodu var; hepsi `reason`
 * taşır. Tek bir "olmadı" cevabı vermek, masadaki misafiri bir daha
 * denemeye iterdi — oysa bitmiş bir ürünle kapalı bir mutfak, misafir için
 * tamamen farklı iki durumdur.
 *
 * ═══ SIRALAMA KASITLI ═══
 *
 * Kod → hak → masa → şalter → sepet. Önce "bu kapı var mı", sonra "bu
 * hizmet satın alınmış mı", sonra "bu kod bir masaya mı bakıyor", sonra
 * "mutfak açık mı", en son "sepette ne var". Sepeti önce doğrulamak,
 * kapalı bir restoranda misafire ürün ürün hata göstermek olurdu.
 *
 * Requirement IDs: ORDER-GUEST-SUBMIT-01.
 */
final class StoreGuestOrderController extends Controller
{
    /**
     * Bir masanın aynı anda taşıyabileceği AÇIK sipariş sayısı.
     *
     * Kapanan sipariş bu sayıya girmez: akşam boyunca yemek yiyen bir masa
     * ikinci turu veremeseydi ürün masada işe yaramazdı. Sınırın amacı
     * kullanımı kısmak değil, tek bir masadan gelen otomatik yığılmayı
     * mutfağa taşımadan durdurmak.
     */
    public const MAX_OPEN_ORDERS_PER_TABLE = 5;

    public function __construct(
        private readonly QrCodeRepositoryPort $qrCodes,
        private readonly PublicationRepositoryPort $publications,
        private readonly EntitlementRepositoryPort $entitlements,
        private readonly OrderingSwitchPort $orderingSwitch,
        private readonly OrderableMenuPort $orderableMenu,
        private readonly OrderRepositoryPort $orders,
        private readonly BuildOrderLines $buildOrderLines,
        private readonly RecordAnalyticsEvent $recordAnalyticsEvent,
    ) {}

    public function __invoke(Request $request, string $token): SymfonyResponse
    {
        try {
            $qrToken = QrToken::fromString($token);
        } catch (InvalidArgumentException) {
            return GuestDeadEnd::respond($request);
        }

        $qrCode = $this->qrCodes->findActiveByToken($qrToken->value());

        /*
            KAPATILMIŞ KOD, BİLİNMEYEN KODDAN AYIRT EDİLEMEZ
            (`QR-PUBLIC-404-UNIFORM-01`).

            Ayrı cevap vermek, deneyerek "bu token vardı ama kapatıldı"
            bilgisini ölçmeye izin verirdi — yani bir restoranın kaç kod
            bastığı dışarıdan sayılabilirdi.
        */
        if ($qrCode === null || $qrCode->menuId === null) {
            return GuestDeadEnd::respond($request);
        }

        $publication = $this->publications->current($qrCode->workspaceId, $qrCode->menuId);

        if ($publication === null) {
            return GuestDeadEnd::respond($request);
        }

        if (! $this->grantsOrdering($publication->entitlementKeys, $qrCode->workspaceId)) {
            /*
                402 ve hakkın ADI. "Yetkiniz yok" demek sahibi panelde
                aramaya iterdi; hakkın adı, panelde ne aradığını söyler.
            */
            return new JsonResponse([
                'reason' => 'entitlement_required',
                'entitlement' => Entitlement::OrderingBasic->value,
            ], SymfonyResponse::HTTP_PAYMENT_REQUIRED);
        }

        if ($qrCode->diningTableId === null) {
            /*
                Masaya bağlı olmayan kod (giriş kodu, afiş, kartvizit).
                Uydurma bir masaya yazmaktansa dürüstçe reddedilir: yanlış
                masaya düşen bir sipariş, hiç düşmeyen siparişten kötüdür.
            */
            return $this->refuse('table_unknown', SymfonyResponse::HTTP_CONFLICT);
        }

        if (! $this->acceptsOrders($qrCode->workspaceId, $qrCode->locationId)) {
            return $this->refuse('ordering_closed', SymfonyResponse::HTTP_CONFLICT);
        }

        $openOrders = $this->orders->openOrderCountForTable(
            $qrCode->workspaceId,
            $qrCode->locationId,
            $qrCode->diningTableId,
        );

        if ($openOrders >= self::MAX_OPEN_ORDERS_PER_TABLE) {
            return $this->refuse('too_many_open_orders', SymfonyResponse::HTTP_CONFLICT);
        }

        try {
            $draft = $this->buildOrderLines->handle(
                $this->orderableMenu->linesForMenu($qrCode->workspaceId, $qrCode->menuId),
                $this->requestedLines($request),
            );
        } catch (OrderLineRejectedException $rejection) {
            return $this->refuse(
                $rejection->reason,
                SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY,
                $rejection->menuItemId,
            );
        }

        $placedAt = new DateTimeImmutable;
        $visitorKey = VisitorKey::forRequest($request, $qrCode->workspaceId, Carbon::instance($placedAt));

        try {
            $orderId = $this->orders->place(
                $qrCode->workspaceId,
                $qrCode->locationId,
                $qrCode->menuId,
                $qrCode->diningTableId,
                $qrCode->id,
                $publication->id,
                $visitorKey,
                $draft,
                $placedAt,
            );
        } catch (OrderPersistenceFailedException) {
            /*
                YARIM SİPARİŞ YAZILMAZ. Depo işlemi geri alır; misafire
                "tekrar dene" demek, mutfağa hiçbir şey düşmediği için
                güvenlidir.
            */
            return $this->refuse('order_not_saved', SymfonyResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        /*
            ÖLÇÜM SİPARİŞİN İÇİNİ BİLMEZ (`docs/112`).

            Olay satırı ürün adı ve fiyat taşımaz: ölçüm tablosu bir
            raporlama yüzeyidir ve misafirin ne yediği oraya sızarsa, bir
            gün ihracatta da sızar. Sipariş satırının kendisi zaten
            `order_items`'ta ve orası kiracıya kapalı.
        */
        $this->recordAnalyticsEvent->handle(
            $qrCode->workspaceId,
            $qrCode->locationId,
            $qrCode->id,
            $qrCode->menuId,
            AnalyticsEventType::OrderSubmitted,
            $visitorKey,
        );

        return new JsonResponse([
            'orderId' => $orderId,
            'status' => OrderStatus::Pending->value,
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Sipariş hakkı — ÖNCE YAYINA DONMUŞ hak, yoksa canlı plan.
     *
     * `docs/114` §3 Dalga 6: masadaki basılı karekod aynı kâğıttır. Sahip
     * planını düşürdüğünde o kâğıdın gösterdiği yayın değişmez; ödeme
     * gecikmesi masada oturan misafirin siparişini ortasından kesmez. Plan
     * değişikliği BİR SONRAKİ yayında etkisini gösterir.
     *
     * `null` = bu alan eklenmeden önce yapılmış yayın. Boş liste saymak
     * eski yayınları geriye dönük haksız ilan etmek olurdu; o durumda canlı
     * plana düşülür ve bu açıkça yapılır.
     *
     * @param  list<string>|null  $frozen
     */
    private function grantsOrdering(?array $frozen, int $workspaceId): bool
    {
        if ($frozen !== null) {
            return in_array(Entitlement::OrderingBasic->value, $frozen, true);
        }

        return $this->entitlements->forWorkspace($workspaceId)->grants(Entitlement::OrderingBasic);
    }

    /**
     * Şube şalteri CANLI okunur, yayına DONMAZ.
     *
     * Sahip gece 23:00'te sipariş almayı kapattığında karar anında geçerli
     * olmalı: kapalıyken gelen bir sipariş, kimsenin bakmadığı bir kuyruğa
     * düşerdi.
     */
    private function acceptsOrders(int $workspaceId, int $locationId): bool
    {
        return $this->orderingSwitch->acceptsOrders($workspaceId, $locationId);
    }

    /**
     * Gövdeden yalnız `menuItemId` ve `quantity` alınır.
     *
     * Fiyat, ad ve alerjen İSTEMCİDEN OKUNMAZ — sunucu onları menüden
     * bulur (`docs/115` S1). Gövdeye fiyat konabilseydi, sipariş fiyatını
     * misafir belirlerdi.
     *
     * `diningTableId` gönderilirse SESSİZCE YOK SAYILIR: reddetmek, alanın
     * bir anlamı olduğunu ima ederdi.
     *
     * @return list<array{menuItemId:int, quantity:int}>
     */
    private function requestedLines(Request $request): array
    {
        $items = $request->input('items');

        if (! is_array($items)) {
            return [];
        }

        $lines = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $menuItemId = filter_var($item['menuItemId'] ?? null, FILTER_VALIDATE_INT);
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);

            if ($menuItemId === false || $quantity === false) {
                continue;
            }

            $lines[] = ['menuItemId' => $menuItemId, 'quantity' => $quantity];
        }

        return $lines;
    }

    private function refuse(string $reason, int $status, ?int $menuItemId = null): JsonResponse
    {
        $body = ['reason' => $reason];

        if ($menuItemId !== null) {
            // Hangi satırın reddedildiği söylenir: sepette beş ürün varken
            // "bir şey bitmiş" demek, misafire sepeti tek tek denetletirdi.
            $body['menuItemId'] = $menuItemId;
        }

        return new JsonResponse($body, $status);
    }
}
