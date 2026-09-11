<?php

declare(strict_types=1);

namespace App\Domain\Support;

/**
 * Bir cevap denemesinin SABİT sonucu — SUPPORT-REPLY-01.
 *
 * Sebep bir KOD'dur, bir cümle değil: ham sağlayıcı metni (içinde bir
 * anahtar olabilir) ne tarayıcıya ne denetim izine çıkar (`docs/110`
 * P0-06 dersi). Sunucu günlüğü ayrıdır ve orada tam metin durur.
 */
enum SupportReplyOutcome: string
{
    case Sent = 'sent';

    /** Sürücü `log`: gönderim HİÇ denenmedi, satır değişmedi. */
    case NoOutboundTransport = 'no_outbound_transport';

    /** Seçim ya da gönderim patladı; aynı gövde yeniden denenebilir. */
    case SendFailed = 'send_failed';
}
