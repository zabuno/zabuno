<?php

declare(strict_types=1);

namespace App\Application\Support\Port;

use App\Application\Support\Dto\ReceivedSupportRequest;

/**
 * İki gönderim, iki alıcı — FF-201.
 *
 * Her ikisi de `null` (devralındı) ya da kırpılmış bir sebep döner;
 * istisna FIRLATMAZ. Gönderim, kaydın şartı değil yardımcısıdır: taşıyıcı
 * düşünce talep durur ve sebebi satıra yazılır (`docs/93` deseni).
 */
interface SupportNotifierPort
{
    /**
     * Gönderene: referansı, konuyu ve varsa yanıt taahhüdünü taşıyan alındı
     * e-postası.
     *
     * Dışarı giden bir taşıyıcı YOKSA (`mail.default` = `log`, yani kimlik
     * hiçbir kaynaktan gelmemiş) `false` döner ve hiçbir şey denenmez:
     * günlüğe yazılmış bir e-posta müşteriye ulaşmamıştır ve ona
     * "gönderildi" damgası basmak, sahibin kendi gelen kutusu için
     * reddettiğimiz yalanın aynısı olurdu (`docs/93`).
     *
     * @return string|false|null `null` devralındı, dize sebep, `false` hiç denenmedi
     */
    public function acknowledge(ReceivedSupportRequest $request): string|false|null;

    /**
     * Sahibe: yeni talep bildirimi. Adres yapılandırılmamışsa ya da dışarı
     * giden taşıyıcı yoksa `false` döner ve HİÇBİR ŞEY denenmez — bu bir
     * arıza değildir ve damga da atılmaz.
     *
     * @return string|false|null `null` devralındı, dize sebep, `false` hiç denenmedi
     */
    public function notifyOwner(ReceivedSupportRequest $request): string|false|null;
}
