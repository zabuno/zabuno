<?php

declare(strict_types=1);

namespace App\Domain\Platform\Credential;

/**
 * Bir bağlantının SAĞLIĞI — `docs/14` §2a, `docs/95` Faz 3 §Yapışkanlık.
 *
 * "Bilinmiyor" ile "sağlıklı" AYRI durumlardır. Henüz hiç sınanmamış bir
 * bağlantıyı "sağlıklı" saymak, ilk gerçek isteği bir tahmine dayandırırdı;
 * "sağlıksız" saymak ise yeni girilen doğru bir anahtarı kullanılamaz
 * kılardı. Bu yüzden üçüncü bir durum var ve varsayılan odur.
 *
 * Sağlıksız bir bağlantı aday havuzundan GEÇİCİ olarak düşer — otomatik
 * silinmez/iptal edilmez; o insan kararıdır (`docs/95` Faz 3 §Sağlık).
 */
enum ConnectionHealth: string
{
    case Unknown = 'unknown';
    case Healthy = 'healthy';
    case Unhealthy = 'unhealthy';

    /** Aday havuzunda durabilir mi? Bilinmeyen durur — sınanma şansı olsun. */
    public function isRoutable(): bool
    {
        return $this !== self::Unhealthy;
    }
}
