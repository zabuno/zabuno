<?php

declare(strict_types=1);

namespace App\Application\Ordering\UseCase;

use App\Application\Ordering\Exception\InvalidOrderTransitionException;
use App\Application\Ordering\Exception\OrderNotFoundException;
use App\Application\Ordering\Port\OrderRepositoryPort;
use App\Domain\Ordering\OrderActor;
use App\Domain\Ordering\OrderStatus;
use DateTimeImmutable;

/**
 * DURUM GEÇİŞLERİNİN TEK KAPISI — `docs/115` §7 S1 (FF-176).
 *
 * Garson kuyruğu, mutfak monitörü ve misafirin iptal düğmesi; üçü de
 * buradan geçer. Her yüzey kendi güncellemesini yazsaydı, kurallar üç yere
 * kopyalanır ve biri eskidiğinde bunu ancak yanlış bir sipariş mutfağa
 * düştüğünde öğrenirdik.
 *
 * KARAR ALANINDA, YAZMA ATOMİK. Kuralı `OrderStatus` verir; bu sınıf o
 * kararı veritabanına **koşullu** olarak uygular: `WHERE status = $from`.
 * Önce okuyup sonra yazan bir akış, iki garson aynı anda onayladığında
 * ikisine de "onayladın" derdi (`docs/115` G5). Yazma tutmazsa durum
 * yeniden okunur ve ret, siparişin O ANKİ durumunu söyler.
 *
 * ÇERÇEVE YOK: bu katman `Illuminate` bilmez; duvar saati bile dışarıdan
 * girer, böylece "iki saniye sonra ne olur" sorusu sınanabilir.
 */
final class ChangeOrderStatus
{
    public function __construct(private readonly OrderRepositoryPort $orders) {}

    /**
     * @throws OrderNotFoundException bu kiracının bu şubesinde böyle bir sipariş yok
     * @throws InvalidOrderTransitionException geçiş kuralca yasak ya da başkası önce davrandı
     */
    public function handle(
        int $workspaceId,
        int $locationId,
        int $orderId,
        OrderStatus $target,
        OrderActor $actor,
        ?string $rejectionReason = null,
        ?DateTimeImmutable $at = null,
    ): void {
        $current = $this->orders->statusInScope($workspaceId, $locationId, $orderId);

        if ($current === null) {
            throw OrderNotFoundException::inScope($workspaceId, $locationId, $orderId);
        }

        if (! $current->canTransitionTo($target, $actor)) {
            throw InvalidOrderTransitionException::notAllowed($current, $target, $actor);
        }

        $reason = $rejectionReason === null ? null : trim($rejectionReason);

        if ($target === OrderStatus::Rejected && ($reason === null || $reason === '')) {
            // G3: sebep misafirin ekranında görünür. Sebepsiz bir ret, ona
            // yalnız "olmadı" der.
            throw InvalidOrderTransitionException::rejectionNeedsReason($current);
        }

        $written = $this->orders->transition(
            $workspaceId,
            $locationId,
            $orderId,
            $current,
            $target,
            $target === OrderStatus::Rejected ? $reason : null,
            $at ?? new DateTimeImmutable('now'),
        );

        if ($written) {
            return;
        }

        /*
            Yazma TUTMADI: okuduğumuz ile yazdığımız an arasında başka biri
            durumu değiştirdi. Sessiz kalmak, ikinci garsona işin kendisine
            ait olduğunu düşündürürdü.
        */
        $now = $this->orders->statusInScope($workspaceId, $locationId, $orderId);

        if ($now === null) {
            throw OrderNotFoundException::inScope($workspaceId, $locationId, $orderId);
        }

        throw InvalidOrderTransitionException::notAllowed($now, $target, $actor);
    }
}
