<?php

declare(strict_types=1);

namespace App\Application\Ordering\Dto;

/**
 * Geçmişin BİR SAYFASI — `docs/115` Y2.
 *
 * Geçmiş silinmez, dolayısıyla büyür ve bir gün tek istekte okunamaz hâle
 * gelir. Sayfalamayı sonraya bırakmak, o günü ilk fark eden kişinin panelin
 * hiç açılmaması olmasını beklemek olurdu.
 *
 * Kuyruk ve mutfak monitörü sayfalanmaz ve bu bilinçli bir ASİMETRİDİR:
 * ikisi de "şu anda ne var" sorusudur ve cevabı ikinci sayfaya taşan bir
 * mutfak zaten mutfak değildir.
 */
final class OrderHistoryPage
{
    /** @param list<OrderSummary> $data */
    public function __construct(
        public readonly array $data,
        public readonly int $page,
        public readonly int $pageCount,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'data' => array_map(
                static fn (OrderSummary $order): array => $order->toArray(),
                $this->data,
            ),
            'page' => $this->page,
            'pageCount' => $this->pageCount,
        ];
    }
}
