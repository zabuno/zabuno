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
 *
 * `choices` KAPALI UÇLU bir alanı tanımlar (FF-220). Kasadaki her alan
 * serbest metin değildir: ölçüm hedefleri (`ga4`, `yandex_metrica`,
 * `hotjar`) yalnız açık ya da kapalıdır. Onları serbest metin bırakmak,
 * `On`/`evet`/`1` gibi bir yazımın sessizce "kapalı" sayılması demekti — ve
 * sessiz kayıp, bu dikişin tam olarak kaçındığı şeydir
 * (`config/analytics.php` başı). Liste burada durduğu için hem panel doğru
 * denetimi çizebilir hem de kasa bilinmeyen bir değeri REDDEDEBİLİR; iki
 * taraf da aynı tek kaynaktan okur.
 *
 * Sır bir alanın `choices`'ı olmaz: kapalı uçlu bir sır, sır değildir.
 *
 * @phpstan-type ChoiceList list<string>
 */
final readonly class CredentialField
{
    /** @param list<string>|null $choices Kapalı uçlu alanın kabul ettiği TÜM değerler. */
    public function __construct(
        public string $name,
        public bool $secret,
        public bool $required,
        public ?string $default = null,
        public ?array $choices = null,
    ) {
        if ($choices !== null && $secret) {
            throw new \InvalidArgumentException(
                "Kapalı uçlu bir sır alan olamaz: '{$name}'.",
            );
        }

        if ($choices !== null && $choices === []) {
            throw new \InvalidArgumentException(
                "Boş seçenek listesi hiçbir değeri kabul etmez: '{$name}'.",
            );
        }
    }

    /** Verilen değer bu alana yazılabilir mi? Boş dize "dokunma" demektir. */
    public function accepts(string $value): bool
    {
        if ($this->choices === null || $value === '') {
            return true;
        }

        return in_array($value, $this->choices, true);
    }
}
