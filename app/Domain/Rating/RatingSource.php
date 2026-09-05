<?php

declare(strict_types=1);

namespace App\Domain\Rating;

/**
 * BİR SİNYALİN KAYNAĞI — `docs/116` §1 Ö1.
 *
 * Bugün tek kaynak var: misafirin masadan karekod okutup verdiği oy. Ama
 * alan bugünden açılmazsa, ikinci kaynak geldiğinde eski satırların hepsi
 * "kaynağı bilinmiyor" olur ve ağırlıklandırma o günden öncesini kapsayamaz.
 * Sonradan eklemek "geçmiş satırlara ne yazacağız?" sorusunu doğurur ve o
 * sorunun her cevabı bir uydurmadır.
 *
 * ÇEKİRDEK SAĞLAYICI ADI BİLMEZ — bu enum bir istisna gibi görünür ve
 * değildir: burada duran şey sağlayıcının API'si değil, ÖLÇÜMÜN CİNSİDİR.
 * Zomato'dan gelen bir puanla masadan gelen bir puan aynı şey olmadığı için
 * ağırlıkları da farklıdır; bunu adlandırmadan ağırlıklandırmak mümkün
 * değildir. Sağlayıcıya konuşan kod (adaptör) hâlâ dışarıdadır.
 */
enum RatingSource: string
{
    /**
     * `rating_signals.source` sütununun genişliği.
     *
     * PostgreSQL `varchar(n)` sınırını UYGULAR, SQLite hiç uygulamaz — yani
     * sığmayan bir değer yerelde sessizce geçer, dağıtım motorunda isteği
     * reddeder. Sabit burada durur ki yeni bir kaynak eklendiğinde göçle
     * enum arasındaki uyuşmazlık tek yerden görülsün. En uzun değer bugün
     * 15 karakter; 32 hem bugünü hem makul bir yarını taşır.
     */
    public const MAX_VALUE_LENGTH = 32;

    /**
     * Misafir, o masadaki karekodu okutup oy verdi.
     *
     * Ürünün elindeki en güçlü sinyal budur ve rakip bir platformun sahip
     * olmadığı şeydir: o kişi gerçekten oradaydı (`docs/116` §4).
     */
    case GuestScan = 'guest_scan';

    /** Zomato'nun resmî API'sinden alınan puan (`docs/116` §5 D2). */
    case ExternalZomato = 'external_zomato';

    /** Swarm/Foursquare'in resmî API'sinden alınan puan. */
    case ExternalSwarm = 'external_swarm';

    /** Google Haritalar'ın resmî API'sinden alınan puan. */
    case ExternalGoogle = 'external_google';

    /**
     * Sahibin kendi sosyal uygulaması — bir kaynak değil, AYRI BİR ÜRÜN
     * (`docs/116` §5). Buraya yalnız bir adaptör olarak bağlanır; bugün
     * alınan tek önlem, yerinin enum'da açık olmasıdır.
     */
    case SocialApp = 'social_app';

    /**
     * Bu sinyal misafirin fiziksel olarak orada olduğunu KANITLIYOR mu?
     *
     * Dış kaynaktan gelen bir puan bunu kanıtlayamaz: o platformdaki kişi
     * restoranda olmuş da olabilir, olmamış da. Ağırlık farkının gerekçesi
     * tek cümlede budur ve kural tek yerde durur.
     */
    public function provesPresenceAtTheTable(): bool
    {
        return $this === self::GuestScan;
    }
}
