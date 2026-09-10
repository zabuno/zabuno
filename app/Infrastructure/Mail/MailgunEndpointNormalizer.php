<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

/**
 * Kasaya yazılmış Mailgun uç noktasını, taşıyıcının beklediği biçime çevirir.
 *
 * ALAN BİR ADRES GİBİ GÖRÜNÜR, AMA BİR HOST'TUR.
 *
 * Superadmin panelde "uç nokta" yazan bir kutu görür ve Mailgun'un kendi
 * ekranında okuduğu değeri — `https://api.eu.mailgun.net/v3` — olduğu gibi
 * yapıştırır. Bu, istenebilecek en makul davranıştır. Ama Symfony'nin
 * Mailgun taşıyıcısı o değeri bir URL olarak DEĞİL, şablonun ortasına
 * konacak bir host olarak kullanır:
 *
 *     https://{uç nokta}/v3/{alan adı}/messages
 *
 * Tam URL yapıştırıldığında hedef `https://https://api.eu.mailgun.net/v3/v3/…`
 * olur. Böyle bir adres yoktur; istek hiç ulaşmaz. Ekranda tek bir kırmızı
 * satır belirmez, çünkü hata gönderme ekranının çok ötesinde, taşıyıcının
 * içinde doğar.
 *
 * NEDEN "KES YAPIŞTIR" DEĞİL DE "REDDET"?
 *
 * Bir URL'den şemayı ve yolu körlemesine söküp geri kalanı host saymak,
 * `https://api:anahtar@baska-sunucu.example.com/v3` gibi bir değeri de
 * sessizce kabul ederdi — ve Mailgun API anahtarı, kimsenin kastetmediği
 * bir sunucuya `Authorization` başlığıyla giderdi. Bu yüzden burada iki
 * ayrı sonuç vardır: taşınabilir bir host (kabul) ya da `null` (ret).
 * Reddedilen bir değer için çağıran taraf Mailgun'u SEÇMEZ; kimlik hiç
 * yüklenmez ve gönderim sessizce yedeğe DÜŞMEZ — bkz.
 * `VaultMailTransportSelector`.
 *
 * BU SINIF NE DEĞİLDİR: BİR İZİN LİSTESİ.
 *
 * Burada yapılan tek şey TAŞINABİLİRLİK kontrolüdür — "bu değer taşıyıcının
 * host alanına, hiçbir parçası düşürülmeden sığar mı?" Sözdizimsel olarak
 * geçerli HERHANGİ bir host geçer: `relay.example.com`, `10.0.0.7:8443`,
 * `mailgun.net.saldirgan.example` — hepsi kabul edilir ve Mailgun API
 * anahtarı gerçekten oraya gönderilir. Mailgun'un bilinen bölge adlarıyla
 * karşılaştırma YAPILMAZ ve yapılması da bu paketin kararı değildir:
 * operatörün kendi ara sunucusunu ya da yeni bir bölgeyi yazabilmesi
 * kasten açık bırakılmış, operatör denetimindeki bir ayardır.
 *
 * Yani bu dosya "yanlış hedef yazılamaz" GARANTİSİ VERMEZ. Verdiği garanti
 * dardır ve tam olarak şudur: yazılan değerin bir parçası sessizce
 * atılmaz. Girilen host'un doğru host olduğunu doğrulayan tek merci,
 * superadmin panelini kullanan kişidir.
 *
 * TAŞINABİLİRLİK SINIRI (uyumluluk kararı). Taşıyıcı yalnız `host` ve
 * isteğe bağlı `port` taşıyabilir; yol (`/v3` hariç, ki onu taşıyıcı zaten
 * kendisi ekler), sorgu dizesi ve çapa için yeri yoktur. Dolayısıyla
 * `https://gecit.example.com/mailgun/api` gibi bir ara sunucu adresi
 * DESTEKLENMEZ ve budanarak "çalışıyor gibi" yapılmaz — böyle bir uç nokta
 * gerektiğinde taşıyıcı katmanının kendisi genişletilmelidir.
 */
final class MailgunEndpointNormalizer
{
    /**
     * Hiç uç nokta girilmemişken kullanılan resmî varsayılan.
     *
     * `config/services.php` ve `CredentialProvider::Mailgun` alan tanımı
     * aynı değeri taşır; burada tekrarlanması, boş bir kasa değerinin
     * çözücü katmanına hiç uğramadan geldiği durumları da kapsamak içindir.
     */
    public const DEFAULT_HOST = 'api.mailgun.net';

    /**
     * Taşıyıcının kendisi `/v3` ekler; bu yüzden kaydedilmiş değerdeki
     * `/v3` bir bilgi tekrarıdır ve düşürülebilir. Bunun DIŞINDA hiçbir yol
     * düşürülmez.
     */
    private const CARRYABLE_PATHS = ['', '/', '/v3', '/v3/'];

    /**
     * Şema SONUCA TAŞINMAZ; burada yalnız elenir.
     *
     * Taşıyıcı isteği her zaman `services.mailgun.scheme` (https) ile kurar,
     * dolayısıyla `http://` yazılmış bir değer cleartext'e DÜŞMEZ — yalnız
     * yazıldığı gibi onurlandırılmaz. Bu listenin işi, `javascript:` ya da
     * `ftp:` gibi bir web adresi bile olmayan girdilerin host sanılmasını
     * engellemektir.
     */
    private const ALLOWED_SCHEMES = ['http', 'https'];

    /**
     * @return string|null Taşıyıcıya verilebilecek `host` ya da `host:port`;
     *                     değer taşınamıyorsa `null` (ret).
     */
    public function normalize(?string $configured): ?string
    {
        $value = trim((string) $configured);

        if ($value === '') {
            return self::DEFAULT_HOST;
        }

        /*
            Şemasız yapıştırma (`api.eu.mailgun.net`) en yaygın ve en doğru
            biçimdir. `parse_url` şemasız bir değeri güvenilir biçimde
            ayrıştırmaz — `host:8443` gibi bir girdiyi şema sanır — bu
            yüzden ayrıştırmadan önce geçici bir şema eklenir. Eklenen şema
            yalnız ayrıştırma içindir; sonuca taşınmaz.
        */
        $parsed = parse_url(str_contains($value, '://') ? $value : 'https://'.$value);

        if (! is_array($parsed)) {
            return null;
        }

        // Sır taşıyabilecek her parça: kimlik, sorgu, çapa. Hiçbiri host'a
        // sığmaz ve hiçbiri sessizce atılmaz.
        if (isset($parsed['user']) || isset($parsed['pass']) || isset($parsed['query']) || isset($parsed['fragment'])) {
            return null;
        }

        if (! in_array(strtolower($parsed['scheme'] ?? ''), self::ALLOWED_SCHEMES, true)) {
            return null;
        }

        if (! in_array($parsed['path'] ?? '', self::CARRYABLE_PATHS, true)) {
            return null;
        }

        $host = strtolower((string) ($parsed['host'] ?? ''));

        if (! $this->isCarryableHost($host)) {
            return null;
        }

        $port = $parsed['port'] ?? null;

        if ($port !== null && ($port < 1 || $port > 65535)) {
            return null;
        }

        return $port === null ? $host : $host.':'.$port;
    }

    /**
     * Nokta ile ayrılmış, harf/rakam ile başlayıp biten etiketler. IPv4
     * adresi de bu kalıba uyar. Köşeli parantezli IPv6 KASTEN dışarıdadır:
     * taşıyıcının host+port birleştirmesi o biçimi güvenle üretmez, ve
     * "belki çalışır" bir adrese sır göndermek bu dosyanın var oluş
     * sebebine aykırıdır.
     *
     * Bu kontrol BİÇİMSELDİR: hangi host'un yazıldığına değil, yazılanın
     * bir host olarak taşınıp taşınamayacağına bakar (bkz. sınıf
     * belgesindeki "bu sınıf ne değildir" bölümü).
     */
    private function isCarryableHost(string $host): bool
    {
        if ($host === '' || strlen($host) > 253) {
            return false;
        }

        return preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/', $host) === 1;
    }
}
