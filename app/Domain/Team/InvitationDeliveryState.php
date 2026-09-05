<?php

declare(strict_types=1);

namespace App\Domain\Team;

/**
 * Davet e-postasının BİLİNEN hâli (`docs/110` P0-06).
 *
 * Üç hâl var, iki değil. "Gönderildi" ile "gönderilemedi" arasındaki
 * boşlukta, teslim geçmişi hiç tutulmamış eski davetler duruyor: onları
 * `Sent` saymak sahibe yapmadığımız bir işi yaptığımızı söylemek,
 * `Failed` saymak ise olmayan bir arıza uydurmak olurdu.
 *
 * `Sent` de bir söz DEĞİLDİR: taşıyıcı mesajı hatasız devraldı demektir,
 * "gelen kutusuna düştü" demek değil. Gelen kutusuna düştüğünü buradan
 * bilemeyiz ve ekran metni de bunu iddia etmez.
 */
enum InvitationDeliveryState: string
{
    case Sent = 'sent';
    case Failed = 'failed';
    case Unknown = 'unknown';

    /**
     * Satırın iki sütunundan hâli türetir.
     *
     * Türetme TEK YERDE durur: iki ayrı sorgunun aynı satırı farklı
     * yorumlaması, ekranla listenin birbirini yalanlaması demekti.
     */
    public static function fromRow(mixed $deliveredAt, mixed $deliveryFailure): self
    {
        if (is_string($deliveryFailure) && $deliveryFailure !== '') {
            return self::Failed;
        }

        return $deliveredAt === null ? self::Unknown : self::Sent;
    }
}
