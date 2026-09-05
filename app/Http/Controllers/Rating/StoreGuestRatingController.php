<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rating;

use App\Application\Publication\Port\PublicationRepositoryPort;
use App\Application\QrDestination\Port\QrCodeRepositoryPort;
use App\Application\Rating\Port\RatableMenuPort;
use App\Application\Rating\UseCase\RecordGuestRating;
use App\Domain\QrDestination\QrToken;
use App\Domain\Rating\RatingAlgorithm;
use App\Domain\Rating\RatingSubject;
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
 * MİSAFİRİN OYU — `docs/116` §4 (P4).
 *
 * ═══ BU UCUN TEK SÖZÜ: OY VEREN KİŞİ ORADAYDI ═══
 *
 * `docs/116` §4: *"Oy vermek için o masadan karekod okutmuş olmak
 * gerekir."* Algoritma dosyası masadan gelen oya her dış kaynaktan yüksek
 * ağırlık veriyor ve bunun tek dayanağı bu denetleyicidir. Masa bağlamı
 * doğrulanmasaydı o ağırlık farkı ölçülmemiş bir iddia olurdu.
 *
 * ═══ SIRALAMA KASITLI ═══
 *
 * Kod → yayın → masa → ölçek → ürün. Önce "bu kapı var mı", sonra
 * "arkasında yayınlanmış bir menü var mı", sonra "bu kod bir masaya mı
 * bakıyor", sonra "bu sayı bu ölçekte anlamlı mı", en son "bu tabak
 * gerçekten bu menüde mi". Sipariş ucundaki sıra ile aynı mantık: ucuz ve
 * belirleyici olan önce sorulur.
 *
 * ═══ ÖLÇÜM OLAYI YAZILMAZ ═══
 *
 * Sipariş ucu bir `analytics_events` satırı yazıyor; burası yazmıyor ve bu
 * bilinçli. Oyun kendisi ZATEN bir ölçüm satırıdır (`rating_signals`) ve
 * kaynağını, zamanını, masasını, ziyaretçisini taşır. İkinci bir olay
 * yazmak aynı şeyi iki tabloda saymak olurdu — ve iki sayaç bir gün
 * ayrışır.
 *
 * Requirement ID'leri: RATING-GUEST-SCAN-01, RATING-GUEST-CHANGED-MIND-02,
 * RATING-GUEST-BURST-03, RATING-GUEST-RANGE-05, RATING-GUEST-ITEM-06.
 */
final class StoreGuestRatingController extends Controller
{
    public function __construct(
        private readonly QrCodeRepositoryPort $qrCodes,
        private readonly PublicationRepositoryPort $publications,
        private readonly RatableMenuPort $ratableMenu,
        private readonly RecordGuestRating $recordGuestRating,
    ) {}

    public function __invoke(Request $request, string $token): SymfonyResponse
    {
        try {
            $qrToken = QrToken::fromString($token);
        } catch (InvalidArgumentException) {
            return GuestDeadEnd::respond($request);
        }

        $qrCode = $this->qrCodes->findActiveByToken($qrToken->value());

        // Kapatılmış kod, bilinmeyen koddan ayırt edilemez
        // (`QR-PUBLIC-404-UNIFORM-01`): ayrı cevap vermek, bir restoranın
        // kaç kod bastığını dışarıdan saydırırdı.
        if ($qrCode === null || $qrCode->menuId === null) {
            return GuestDeadEnd::respond($request);
        }

        if ($this->publications->current($qrCode->workspaceId, $qrCode->menuId) === null) {
            return GuestDeadEnd::respond($request);
        }

        $algorithm = RatingAlgorithm::current();
        $abuse = $algorithm->abuseRules();

        if ($abuse->requiresTableScan() && $qrCode->diningTableId === null) {
            /*
                AFİŞ, KARTVİZİT VE GİRİŞ KODU BİR MASA DEĞİLDİR.

                Bu kodları okutan kişi restoranda olabilir de olmayabilir
                de. Oyu kabul edip "masadan geldi" diye saymak, ürünün en
                güçlü sinyalini kendi elimizle sulandırmak olurdu. Ret
                DÜRÜSTTÜR: sebebini söyler, boş bir ekran vermez.
            */
            return $this->refuse('table_unknown', SymfonyResponse::HTTP_CONFLICT);
        }

        $score = filter_var($request->input('score'), FILTER_VALIDATE_INT);

        if ($score === false || $score < 1 || $score > $algorithm->scaleMax) {
            /*
                KIRPMA YASAK. 9 puanı sessizce 5'e çekmek, misafirin
                vermediği bir oyu ona atfetmek olurdu — ve defter değişmez
                olduğu için o uydurma sonsuza kadar orada kalırdı.

                Ölçek ALGORİTMA DOSYASINDAN okunur; uca gömülseydi dosya
                ile uç bir gün farklı ölçeklerde çalışırdı.
            */
            return $this->refuse('score_out_of_range', SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $menuItemId = filter_var($request->input('menuItemId'), FILTER_VALIDATE_INT);

        $productId = $menuItemId === false
            ? null
            : $this->ratableMenu->productForMenuItem($qrCode->workspaceId, $qrCode->menuId, $menuItemId);

        if ($productId === null) {
            /*
                KİMLİK GÖVDEDEN GELİYOR, DOLAYISIYLA DOĞRULANIYOR.

                Doğrulanmasaydı, bu masadan okutulan karekodla başka bir
                restoranın ürününe oy verilebilirdi: gövdeye yabancı bir
                sayı yazmak yeterdi.
            */
            return $this->refuse('item_unavailable', SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $now = new DateTimeImmutable;

        $this->recordGuestRating->handle(
            $qrCode->workspaceId,
            // Puan TABAĞA yazılır, menü satırına değil: fiyatı değiştiği
            // için yeniden kurulan bir satır ürünün puanını sıfırlayamaz.
            RatingSubject::Product,
            $productId,
            $score,
            $algorithm->scaleMax,
            // Ham IP ve tarayıcı bilgisi SAKLANMAZ; yalnız günlük dönen bir
            // tuzla türetilmiş özet yazılır (`docs/68`).
            VisitorKey::forRequest($request, $qrCode->workspaceId, Carbon::instance($now)),
            $qrCode->id,
            (int) $qrCode->diningTableId,
            $abuse,
            $now,
        );

        /*
            CEVAP HER ZAMAN AYNI.

            Oy işaretlenmiş olsa bile (yığılma) misafir aynı cevabı alır.
            "Bu oy sayılmadı" diyen bir yanıt, deneyerek tavanı bulmanın en
            ucuz yolu olurdu — ve dürüst bir masayı da suçlu gibi
            hissettirirdi. Söylenen şey doğru: fikri kaydedildi. Sayılıp
            sayılmadığı misafirin değil, algoritmanın sorusudur ve cevabı
            defterde yazılıdır.
        */
        return new JsonResponse(['status' => 'recorded'], SymfonyResponse::HTTP_CREATED);
    }

    private function refuse(string $reason, int $status): JsonResponse
    {
        return new JsonResponse(['reason' => $reason], $status);
    }
}
