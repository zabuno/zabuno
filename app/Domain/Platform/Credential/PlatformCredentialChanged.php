<?php

declare(strict_types=1);

namespace App\Domain\Platform\Credential;

/**
 * Kasada BİR ŞEY DEĞİŞTİ — hangi sağlayıcı, hangi bağlantı, ne oldu.
 *
 * Neden bir olay: kasadan okunan bir değer önbelleğe alınabilir (ölçüm
 * ayarları CSP başlığını belirliyor ve her istekte veritabanına gitmek kabul
 * edilemez, `docs/135` §5). Önbellek varsa geçersiz kılma da olmak
 * zorundadır; yoksa sahip panelden bir hedefi açar ve "neden değişmedi"
 * sorusunun cevabı "sunucuyu yeniden başlat" olur.
 *
 * Alternatifi, kasa deposunun ölçüm önbelleğini ADIYLA çağırmasıydı: o
 * durumda depo, kendisini okuyan her tüketiciyi tanımak zorunda kalırdı ve
 * ikinci tüketici geldiği gün ya unutulur ya da depo şişerdi. Olay, bu
 * bağımlılığı tersine çevirir — depo yalnız \"değişti\" der, kimin
 * dinlediğini bilmez.
 *
 * SIR TAŞIMAZ ve taşıyamaz: alanları yalnız kimlik ve eylem adıdır.
 */
final readonly class PlatformCredentialChanged
{
    public function __construct(
        public CredentialProvider $provider,
        public ?int $connectionId,
        public string $action,
    ) {}
}
