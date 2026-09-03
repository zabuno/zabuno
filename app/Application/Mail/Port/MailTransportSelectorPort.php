<?php

declare(strict_types=1);

namespace App\Application\Mail\Port;

/**
 * Bu gönderim hangi posta sürücüsüyle çıkacak?
 *
 * Üretimde `.env` konteynerin içinde yaşamaz ve config önyüklemede
 * dondurulur; kasadan girilen bir anahtar ise çalışma zamanında değişir.
 * Bu port, göndermeden HEMEN ÖNCE kimlik-bilgisini kasadan (yoksa env'den)
 * çözer, sürücü yapılandırmasını tazeler ve kullanılacak sürücünün adını
 * döner. Böylece superadmin UI'dan anahtar girdiği an posta çalışır —
 * sunucuya dokunmadan, yeniden deploy etmeden.
 */
interface MailTransportSelectorPort
{
    public function select(): string;
}
