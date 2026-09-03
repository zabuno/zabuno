<?php

declare(strict_types=1);

namespace App\Domain\Platform\Credential;

/**
 * Bir sağlayıcı kimlik-bilgisinin TEK bir alanı — adı ve sınıfı.
 *
 * `secret` ile düz alan BİLEREK ayrıdır: yalnız `secret` alanlar şifrelenir,
 * yalnız `secret` alanlar maskelenir ve yalnız `secret` alanlar asla geri
 * okunmaz. `endpoint` gibi düz bir alan panelde açıkça görünebilir; bir API
 * anahtarı görünemez. Ayrımı bir bayrağa değil, alan tanımına gömüyoruz ki
 * yeni bir alan eklendiğinde geliştirici sınıfını söylemek zorunda kalsın.
 */
final readonly class CredentialField
{
    public function __construct(
        public string $name,
        public bool $secret,
        public bool $required,
        public ?string $default = null,
    ) {}
}
