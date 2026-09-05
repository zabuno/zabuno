<?php

declare(strict_types=1);

namespace App\Application\Ordering\Port;

use App\Application\Ordering\Dto\OrderHistoryPage;
use App\Application\Ordering\Dto\OrderSummary;

/**
 * PANELİN OKUDUĞU SİPARİŞLER — yazma portundan AYRI (`docs/115` S4/S5/S6).
 *
 * `OrderRepositoryPort` siparişi yazar ve durumunu değiştirir; bu port
 * yalnız okur. Ayrılmalarının sebebi mimari zarafet değil, iki somut şey:
 *
 * 1. Yazma portu misafirin gönderme yolunda da kullanılıyor. Oraya üç liste
 *    sorgusu eklemek, sipariş verme yolunun hiç ihtiyaç duymadığı bir
 *    sözleşmeyi taşıması demekti.
 * 2. Okuma tarafı SATIRLARI da getirir (alerjen kopyasıyla). Yazma tarafı
 *    hiçbir zaman satır okumaz; tek bir portta ikisi olsaydı, "bu metot
 *    satırları da getirir mi" sorusu her çağrı yerinde yeniden sorulurdu.
 *
 * ÜÇ METOT DA KİRACI VE ŞUBE ALIR ve ikisi de sorgunun `WHERE`'inde durur.
 * Kapsamı çağırana bırakmak, bir gün birinin unutmasını beklemektir.
 */
interface OrderQueryPort
{
    /**
     * Garson kuyruğu: yalnız `pending`, EN ESKİ ÜSTTE (`docs/115` G1).
     *
     * Sıra bir nezaket değil: servis anında misafirin ne kadar beklediğinin
     * tek kaydıdır ve en yeni üstte olsaydı, ilk gelen en son bakılan olurdu.
     *
     * @return list<OrderSummary>
     */
    public function pending(int $workspaceId, int $locationId, int $limit): array;

    /**
     * Mutfak monitörü: yalnız `OrderStatus::isVisibleToKitchen()` durumları.
     *
     * Liste burada ELLE YAZILMAZ, alandan okunur — mutfağın ne göreceği bir
     * sorgu detayı değil, bu ürünün kemik kuralıdır.
     *
     * @return list<OrderSummary>
     */
    public function kitchenBoard(int $workspaceId, int $locationId, int $limit): array;

    /**
     * Geçmiş: her durum, EN YENİ ÜSTTE, sayfalanmış (`docs/115` Y2).
     *
     * Açık siparişler de listede; "bugün ne oldu" sorusunun cevabı yalnız
     * kapananlardan oluşmaz.
     */
    public function history(int $workspaceId, int $locationId, int $page, int $perPage): OrderHistoryPage;
}
