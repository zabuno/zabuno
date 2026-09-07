<?php

declare(strict_types=1);

namespace App\Domain\Billing;

/**
 * e-arşiv/e-fatura gönderim durumu (docs/130 §K4).
 *
 * SÖZLÜK BİLEREK İKİ DEĞERLİDİR ve "gönderildi" bunlardan biri DEĞİLDİR.
 * Türkiye'de e-arşiv/e-fatura kesmek kayıtlı bir entegratör ya da GİB
 * portalı ister; bugün ikisi de yok. Var olmayan bir gönderimi anlatan bir
 * durum eklemek, vergi denetiminde kesilmemiş bir faturayı kesilmiş
 * sanmaktır — ürünün sözlüğü buna izin vermez.
 */
enum EArchiveDispatchState: string
{
    /** Sağlayıcı hiç bağlanmadı: yol YOK, "başarısız" da değil. */
    case NotConfigured = 'not_configured';

    /** Belge kesildi, dışarıya hiçbir şey gönderilmedi. */
    case NotDispatched = 'not_dispatched';
}
