<?php

declare(strict_types=1);

namespace App\Infrastructure\Analytics;

use App\Application\Platform\Port\CredentialResolverPort;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Support\Analytics\AnalyticsConfiguration;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Throwable;

/**
 * Ölçüm ayarlarını KASADAN (yoksa env'den) çözer — `docs/135`.
 *
 * `VaultMailTransportSelector`'ın ölçüm karşılığı ve aynı deseni izler:
 * öncelik sırası (`KASA > env`) burada DEĞİL, `CredentialResolverPort`'un
 * içinde yaşar; bu sınıf yalnız çözülen değeri ölçüm yapılandırmasına
 * çevirir. İkinci bir öncelik mantığı yazmak, bir gün ikisinin ayrışacağı
 * anlamına gelirdi.
 *
 * ═══ İKİ TUZAK, İKİ ÇÖZÜM ═══
 *
 * 1. KASA BİR VERİTABANIDIR; SAYFA ÇİZİMİ ONA BAĞLI DEĞİLDİR.
 *
 *    Kurumsal site bilerek veritabanısız çizilebiliyor
 *    (`site:export-static`). Ölçüm okuması bir sayfa isteğini ÇÖKERTEMEZ:
 *    veritabanı yoksa, tablo yoksa ya da bağlantı düşmüşse istisna yutulur
 *    ve yapılandırmaya (env) düşülür. Kimlik oradan da gelmiyorsa ölçüm
 *    sessizce KAPALIDIR — bugünkü "kimlik yoksa ölçüm yok" davranışının
 *    aynısı — ve sayfa çizilmeye devam eder.
 *
 *    Yutulan istisna bir kural değil bir istisnadır: yalnız BURADA, yalnız
 *    okuma yolunda geçerlidir. Panelin yazma yolu istisnayı yutmaz; orada
 *    sessiz bir başarısızlık, sahibin kaydettiğini sanması demek olurdu.
 *
 * 2. CSP HER YANITTA ÜRETİLİR; HER İSTEKTE VERİTABANINA GİDİLMEZ.
 *
 *    Çözülen değer önbelleğe alınır. Önbellek varsa geçersiz kılma da
 *    olmak zorundadır, yoksa sahibin panelden açtığı bir hedef "sunucuyu
 *    yeniden başlat" diyene kadar CSP'ye yansımaz. İki mekanizma birlikte:
 *
 *      • ANINDA: kasadaki her mutasyon `PlatformCredentialChanged` olayını
 *        doğurur ve dinleyici bu anahtarı düşürür. Paylaşılan bir önbellek
 *        deposunda (redis/veritabanı/dosya) bu, bütün düğümleri kapsar.
 *      • TAVAN: 60 saniyelik ömür. Her düğümün KENDİ önbelleği varsa
 *        (ör. `array`), olay yalnız yazan düğümde çalışır; ömür, geri
 *        kalanının bayatlığını bir dakikayla sınırlar.
 *
 *    Süre, sahibin bir ayarı açıp sayfayı yenilerken beklemeye razı
 *    olacağı en uzun aralık olarak seçildi; daha uzunu "çalışmıyor" diye
 *    okunur, daha kısası kasanın okunma sıklığını sebepsiz artırırdı.
 */
final class VaultAnalyticsSettings
{
    /**
     * Sürüm eki BİLEREK var: alan şeması değişirse anahtar da değişir ve
     * eski biçimdeki bir kayıt yeni kodla okunmaya çalışılmaz.
     */
    private const CACHE_KEY = 'analytics.vault.v1';

    private const TTL_SECONDS = 60;

    /** İstek içi bellek — CSP başlığı ve görünüm aynı cevabı iki kez sormasın. */
    private ?AnalyticsConfiguration $memo = null;

    public function __construct(
        private readonly CredentialResolverPort $resolver,
        private readonly CacheRepository $cache,
    ) {}

    public function configuration(): AnalyticsConfiguration
    {
        return $this->memo ??= AnalyticsConfiguration::fromConfig($this->vaultValues());
    }

    /** Kasa değişti: bir sonraki okuma taze olsun. */
    public function forget(): void
    {
        $this->memo = null;

        try {
            $this->cache->forget(self::CACHE_KEY);
        } catch (Throwable) {
            // Önbellek deposu da düşmüş olabilir. Düşüremediğimiz bir
            // anahtar en fazla bir dakika bayat kalır (ömür); bu yüzden
            // burada patlamanın hiçbir kazancı yok.
        }
    }

    /** @return array<string, string> */
    private function vaultValues(): array
    {
        try {
            /** @var mixed $cached */
            $cached = $this->cache->remember(
                self::CACHE_KEY,
                self::TTL_SECONDS,
                fn (): array => $this->resolver->resolve(CredentialProvider::GoogleTagManager),
            );
        } catch (Throwable) {
            return [];
        }

        if (! is_array($cached)) {
            return [];
        }

        $out = [];
        foreach ($cached as $name => $value) {
            $out[(string) $name] = (string) $value;
        }

        return $out;
    }
}
