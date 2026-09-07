<?php

declare(strict_types=1);

namespace App\Application\Billing\Port;

use App\Application\Billing\Exception\EArchiveGatewayNotConfiguredException;
use App\Domain\Billing\EArchiveDispatchState;

/**
 * e-arşiv/e-fatura sağlayıcısının YERİ (docs/107 Faz 1.4, docs/130 §K4).
 *
 * Bugün gerçek bir uygulaması YOKTUR ve bu bilinçlidir: Türkiye'de e-arşiv
 * ya da e-fatura kesmek kayıtlı bir entegratörle sözleşme ya da GİB
 * portalı ister. Sözleşme sahibin işidir ve henüz yapılmadı.
 *
 * Bu portun tek kuralı: OLMAYAN BİR UYGULAMA SESSİZCE BAŞARILI DÖNMEZ.
 * `submit` yapılandırılmamışken açıkça durur. Sahte bir "gönderildi"
 * dönüşü, bir gün vergi denetiminde kesilmemiş bir faturayı kesilmiş
 * sanmak demektir; bu ürünün taşıyabileceği bir risk değildir.
 */
interface EArchiveGatewayPort
{
    public function isConfigured(): bool;

    public function state(): EArchiveDispatchState;

    /**
     * @return string Sağlayıcının belge kimliği.
     *
     * @throws EArchiveGatewayNotConfiguredException Bugün her zaman.
     */
    public function submit(int $invoiceId): string;
}
