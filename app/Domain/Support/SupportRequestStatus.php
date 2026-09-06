<?php

declare(strict_types=1);

namespace App\Domain\Support;

/**
 * Talebin durumu — FF-201 (`docs/125`).
 *
 * Üç hâl, fazlası değil: alındı, cevaplandı, kapandı. "Bekliyor",
 * "inceleniyor", "yükseltildi" gibi ara hâller bugün hiçbir ekranda
 * karşılık bulmuyor; olmayan bir süreci durumla anlatmak, müşteriye
 * olmayan bir işleyişi vaat etmek olurdu.
 *
 * `answered` ile `closed` ayrıdır çünkü ikisi farklı şey söyler: ilki
 * "sana yazdık", ikincisi "bu iş bitti". Cevaplanmış ama kapanmamış bir
 * talep, müşterinin dönüşünü bekleyen taleptir.
 */
enum SupportRequestStatus: string
{
    case Received = 'received';
    case Answered = 'answered';
    case Closed = 'closed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
