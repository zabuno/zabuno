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

    /**
     * "BU ŞUBE BU KİRACININ MI?" — okuma da yazma da değil, KAPSAM sorusu.
     *
     * Ayrı bir soru olarak duruyor çünkü RET SEBEPLERİNİN SIRASI bir ürün
     * kararıdır (`docs/115` Y3). Kiracıya ait olmayan bir şube için "senin
     * planında sipariş alma yok" demek, o şubenin varlığını kabul etmek ve
     * yanlış bir çıkış yolu göstermek olurdu: sahip planını yükseltir, sonra
     * yine hiçbir şey olmaz.
     *
     * `acceptsOrders()` bu soruyu cevaplayamaz ve bu bilerek böyle: yok olan
     * şube ile kapalı şube ondan aynı `false` ile döner, çünkü var olmayan
     * bir şubeyi "sipariş alıyor" saymak her zaman daha kötüdür.
     */
    public function belongsToWorkspace(int $workspaceId, int $locationId): bool;

    /**
     * Şalteri çevirir; ŞUBE BU KİRACIYA AİTSE `true` döner (`docs/115` Y1).
     *
     * Dönüş değeri "yazdım mı" değil, "BÖYLE BİR ŞUBE VAR MI" sorusunun
     * cevabıdır — çağıran katman `false` gördüğünde 404 der. Bu okuma yolunun
     * aynısıdır: kapsam koşulu sorgunun içindedir ve çağırana bırakılmaz.
     *
     * Yazma tarafı okuma tarafıyla AYNI portta yaşıyor, çünkü ikisi de tek
     * bir soruya bakıyor: "bu şube şu an sipariş alıyor mu?". Ayrı bir yazar
     * portu, aynı sütunun iki farklı kapsam kuralına sahip olabileceği bir
     * gün yaratırdı.
     */
    public function setAcceptsOrders(int $workspaceId, int $locationId, bool $acceptsOrders): bool;
}
