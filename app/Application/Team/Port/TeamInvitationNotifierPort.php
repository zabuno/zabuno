<?php

declare(strict_types=1);

namespace App\Application\Team\Port;

use App\Application\Team\Dto\IssuedTeamInvitation;

/**
 * Daveti e-postayla çıkarır — ve çıkmadıysa SEBEBİNİ söyler.
 *
 * Dönüş değeri `void` DEĞİL: "gönderdim" ile "gönderemedim" arasındaki fark
 * bu ürünün en pahalı farkıdır ve çağıranın onu görmesi gerekir. Sebep bir
 * istisna olarak da fırlatılabilirdi; ama o zaman her çağıran kendi
 * `try/catch`ini yazardı ve biri unutulduğu gün gönderim yine sessizce
 * düşerdi.
 */
interface TeamInvitationNotifierPort
{
    /**
     * Gönderim başarılıysa `null`, başarısızsa KIRPILMIŞ sebep döner.
     *
     * Dönen metin kayda geçmek içindir, ekrana basmak için değil: sağlayıcı
     * cevapları uç adresi, alan adı ve ham gövde taşıyabilir.
     */
    public function notify(IssuedTeamInvitation $invitation): ?string;
}
