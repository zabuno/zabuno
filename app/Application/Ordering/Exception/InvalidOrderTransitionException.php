<?php

declare(strict_types=1);

namespace App\Application\Ordering\Exception;

use App\Domain\Ordering\OrderActor;
use App\Domain\Ordering\OrderStatus;
use RuntimeException;

/**
 * GEÇERSİZ GEÇİŞ SESSİZCE YUTULMAZ — `docs/115` §7 S1, G5.
 *
 * Bu istisnanın var olma sebebi tek bir davranış: **ikinci onay denemesi
 * "tamam" demez.** İki garson aynı siparişi aynı anda onayladığında,
 * ikincisi işin kendisine ait olduğunu sanmamalı; ekranda siparişin O ANKİ
 * durumunu görmeli.
 *
 * Bu yüzden istisna bir mesaj değil, iki DURUM taşır: ne denendi ve şu an
 * ne. Çağıran katman cümleyi kendi diliyle kurar; burada karar donar.
 */
final class InvalidOrderTransitionException extends RuntimeException
{
    private function __construct(
        public readonly OrderStatus $current,
        public readonly ?OrderStatus $attempted,
        public readonly OrderActor $actor,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function notAllowed(OrderStatus $current, OrderStatus $attempted, OrderActor $actor): self
    {
        return new self(
            $current,
            $attempted,
            $actor,
            sprintf(
                'Sipariş "%s" durumunda; "%s" bu durumdan (%s) yapılamaz.',
                $current->value,
                $attempted->value,
                $actor->value,
            ),
        );
    }

    /**
     * Sebepsiz ret (`docs/115` G3).
     *
     * Sebep misafirin ekranında görünür; sebepsiz bir ret ona yalnız
     * "olmadı" der ve neyi düzelteceğini bilmez.
     */
    public static function rejectionNeedsReason(OrderStatus $current): self
    {
        return new self(
            $current,
            OrderStatus::Rejected,
            OrderActor::Staff,
            'Ret sebebi zorunludur: sebep misafirin ekranında görünür.',
        );
    }
}
