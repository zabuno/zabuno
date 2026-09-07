<?php

declare(strict_types=1);

namespace App\Domain\Rating;

/**
 * DIŞ SİSTEMİN ADI — `docs/116` §1 Ö4 (P7).
 *
 * ═══ NEDEN `RatingSource` YETMİYOR ═══
 *
 * `RatingSource` bir SİNYALİN cinsini söyler ve içinde `guest_scan` de
 * vardır — masadan gelen oyun bir "dış kimliği" yoktur ve olamaz. Eşleme
 * tablosuna `RatingSource` koysaydık, `guest_scan` değerini taşıyan bir
 * eşleme satırı yazmak MÜMKÜN olurdu: "masadan gelen oyun Google'daki
 * kimliği" gibi anlamsız bir satır. Tip bunu engelleyemiyorsa, bir gün
 * biri yazar.
 *
 * Bu enum yalnız DIŞARIDA kimliği olan sistemleri sayar ve her biri
 * `ratingSource()` ile kendi sinyal kaynağına bağlıdır. Bağ tek yerde
 * durur: yeni bir kaynak eklendiğinde ağırlık, kaynak ve eşleme aynı anda
 * görülür.
 *
 * ═══ ÇEKİRDEK SAĞLAYICI ADI BİLMEZ — BU BİR İSTİSNA DEĞİL ═══
 *
 * `RatingSource`'un kendi gerekçesi burada da geçerlidir: burada duran şey
 * sağlayıcının API'si değil, ÖLÇÜMÜN CİNSİDİR. Sağlayıcıya konuşan kod
 * (adaptör) hâlâ dışarıdadır ve bu pakette HİÇ YOKTUR (P8 ayrı pakettir).
 */
enum ExternalSystem: string
{
    /**
     * `external_references.external_system` sütununun genişliği.
     *
     * PostgreSQL `varchar(n)` sınırını UYGULAR, SQLite hiç uygulamaz — yani
     * sığmayan bir değer yerelde sessizce geçer, dağıtım motorunda isteği
     * reddeder. Sabit burada durur ki göçle enum arasındaki uyuşmazlık tek
     * yerden görülsün.
     */
    public const MAX_VALUE_LENGTH = 32;

    case Zomato = 'zomato';

    case Swarm = 'swarm';

    case Google = 'google';

    /**
     * Sahibin kendi sosyal uygulaması — bir kaynak değil, AYRI BİR ÜRÜN
     * (`docs/116` §5). Bugün alınan tek önlem, eşleme tablosunun onu da
     * taşıyabilmesidir.
     */
    case SocialApp = 'social_app';

    /**
     * Bu sistemden gelen bir puanın deftere hangi kaynakla yazılacağı.
     *
     * Eşleme ile sinyal arasındaki tek bağ budur ve tek yerde durur: iki
     * ayrı yerde eşleştirilseydi, bir gün ayrışırlar ve bir kaynağın
     * ağırlığı başka bir kaynağın puanına uygulanırdı.
     */
    public function ratingSource(): RatingSource
    {
        return match ($this) {
            self::Zomato => RatingSource::ExternalZomato,
            self::Swarm => RatingSource::ExternalSwarm,
            self::Google => RatingSource::ExternalGoogle,
            self::SocialApp => RatingSource::SocialApp,
        };
    }
}
