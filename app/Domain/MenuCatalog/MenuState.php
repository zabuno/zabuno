<?php

declare(strict_types=1);

namespace App\Domain\MenuCatalog;

/**
 * Bir menünün ŞUBENİN GÜNÜNDEKİ yeri.
 *
 * 2026-09-05'te sahibin çoklu menü kararıyla (`docs/109` §7.1) bu liste
 * `Draft | Published`'dan `Draft | Active | Disabled`'a geçti. Sebep:
 * "yayınlandı mı" bilgisi zaten `menu_publications` ve canlı yayın
 * işaretçisinde tutuluyordu ve `menus.state` sütununa hiç yazılmıyordu —
 * yani üründe iki kere anlatılan, birinin hiç güncellenmediği bir bilgiydi.
 * Sahibin ihtiyaç duyduğu ayrım ise başkaydı: kaynakta üç hap var ve
 * üçüncüsü "Ramazan (kapalı)". Yani menülerin bir kısmı hazırlanıyor, bir
 * kısmı servis ediliyor, bir kısmı da PARK EDİLMİŞ durumda.
 */
enum MenuState: string
{
    /** Hazırlanıyor: henüz hiçbir saat aralığı sahiplenmemiş. */
    case Draft = 'draft';

    /** Rotasyonda: günün en az bir dakikası bu menüye ait. */
    case Active = 'active';

    /** Park edilmiş: "Ramazan (kapalı)". Silinmedi, ama saat de tutmuyor. */
    case Disabled = 'disabled';
}
