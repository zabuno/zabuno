<?php

declare(strict_types=1);

namespace App\Domain\Support;

/**
 * Bir gönderimin BİLİNEN hâli — FF-201, ekip davetindeki desenin aynısı
 * (`App\Domain\Team\InvitationDeliveryState`, `docs/110` P0-06).
 *
 * Üç hâl var, iki değil. `Sent` bir söz DEĞİLDİR: taşıyıcı mesajı hatasız
 * devraldı demektir, "gelen kutusuna düştü" demek değil. `Unknown`, damgası
 * hiç atılmamış satırdır — onu `Sent` saymak yapmadığımız bir işi yaptık
 * demek, `Failed` saymak olmayan bir arıza uydurmak olurdu.
 *
 * Ekip davetinin enum'u YENİDEN KULLANILMADI: o, davet alanının sözlüğüdür
 * ve destek alanı ona bağımlı olsaydı, davet alanı bir gün değişince destek
 * de kırılırdı. Aynı üç kelime, iki ayrı sahip.
 */
enum DeliveryState: string
{
    case Sent = 'sent';
    case Failed = 'failed';
    case Unknown = 'unknown';

    public static function fromRow(mixed $sentAt, mixed $failure): self
    {
        if (is_string($failure) && $failure !== '') {
            return self::Failed;
        }

        return $sentAt === null ? self::Unknown : self::Sent;
    }
}
