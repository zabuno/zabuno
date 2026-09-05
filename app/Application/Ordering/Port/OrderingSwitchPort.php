<?php

declare(strict_types=1);

namespace App\Application\Ordering\Port;

/**
 * "BU ŞUBE ŞU AN SİPARİŞ ALIYOR MU?" — tek soru, tek port.
 *
 * Ayrı bir port, çünkü bu değer yayına DONMAZ: canlı okunur. Sahip gece
 * 23:00'te sipariş almayı kapattığında karar anında geçerli olmalıdır;
 * yayına dondurulsaydı, kapattığı hizmet yeni bir yayına kadar açık kalır
 * ve kimsenin bakmadığı bir kuyruğa sipariş düşerdi.
 *
 * Yayına donan hak (`menu_publications.entitlements`) ile bu şalter
 * bilerek FARKLI yerlerde yaşıyor: biri "bu hizmet satın alındı mı",
 * öbürü "mutfak şu an açık mı" sorusudur ve ikisi aynı anda farklı
 * cevaplar verebilir.
 */
interface OrderingSwitchPort
{
    /**
     * Şube kimliği kiracı kapsamıyla birlikte sorulur: kapsamsız bir
     * kimlik sorgusu, komşu kiracının şubesini okumanın en sessiz yoludur.
     */
    public function acceptsOrders(int $workspaceId, int $locationId): bool;
}
