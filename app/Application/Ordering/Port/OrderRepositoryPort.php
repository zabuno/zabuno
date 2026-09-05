<?php

declare(strict_types=1);

namespace App\Application\Ordering\Port;

use App\Application\Ordering\Dto\OrderDraft;
use App\Application\Ordering\Exception\OrderPersistenceFailedException;
use App\Domain\Ordering\OrderStatus;
use DateTimeImmutable;

interface OrderRepositoryPort
{
    /**
     * Siparişi ve satırlarını TEK işlemde yazar; kimliğini döner.
     *
     * Yarım sipariş diye bir şey yoktur: satırları yazılmamış bir sipariş,
     * mutfağa boş bir fiş düşürürdü.
     *
     * @throws OrderPersistenceFailedException
     */
    public function place(
        int $workspaceId,
        int $locationId,
        int $menuId,
        int $diningTableId,
        ?int $qrCodeId,
        ?int $publicationId,
        string $visitorKey,
        OrderDraft $draft,
        DateTimeImmutable $placedAt,
    ): int;

    /**
     * Masanın hâlâ beklediği sipariş sayısı.
     *
     * KAPANMIŞ sipariş sayılmaz: akşam boyunca yemek yiyen bir masa ikinci
     * turu verebilmeli.
     */
    public function openOrderCountForTable(int $workspaceId, int $locationId, int $diningTableId): int;

    /**
     * Kiracı VE şube sınırı içinde siparişin O ANKİ durumu; yoksa `null`.
     *
     * İki sütun da `WHERE`'dedir ve bu bir ekran kuralı değildir: ekran bir
     * düğmeyi gizleyebilir, istek yine elle gönderilebilir.
     */
    public function statusInScope(int $workspaceId, int $locationId, int $orderId): ?OrderStatus;

    /**
     * ATOMİK geçiş: yalnız sipariş HÂLÂ `$from` durumundaysa yazar.
     *
     * `true` yazıldı, `false` başkası önce davrandı demektir. Önce okuyup
     * sonra yazan bir akış, iki garsonun aynı anda onayladığı durumda ikisine
     * de "onayladın" derdi (`docs/115` G5).
     *
     * @throws OrderPersistenceFailedException
     */
    public function transition(
        int $workspaceId,
        int $locationId,
        int $orderId,
        OrderStatus $from,
        OrderStatus $to,
        ?string $rejectionReason,
        DateTimeImmutable $at,
    ): bool;
}
