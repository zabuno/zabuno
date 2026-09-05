<?php

declare(strict_types=1);

namespace App\Support\Analytics;

use Illuminate\Http\Request;

/**
 * ÖLÇÜM ONAYI — varsayılan KAPALI, ve bu bir tercih değil.
 *
 * `modules/analytics-consent-tagging.md` modülün amacını "consent-gated
 * üçüncü taraf tag'leri yönetmek" diye yazıyor ve `docs/46` §6 madde 9
 * ("Çerez/izin yönetimi — GTM Consent Mode") bunu yapılmamış olarak
 * işaretliyordu. Modül envanteri ölçtü: kodda **hiç** onay kapısı yoktu.
 * Yani kural yazılıydı, uygulaması yoktu.
 *
 * ═══ NEDEN ÖNCE YÜKLEYİP SONRA SORMAK YETMEZ ═══
 *
 * Consent Mode'un kendi tasarımı konteyneri yükleyip etiketleri sinyale göre
 * kısar. Bu meşru bir yaklaşımdır ama tek başına bu ürün için yetmez: script
 * yüklendiği anda üçüncü tarafa bir istek gitmiş, IP görülmüş olur. "Onay
 * alınmadan ölçüm çalışmaz" cümlesini gerçekten tutmanın yolu, kararı
 * verilene kadar konteyneri HİÇ yüklememektir.
 *
 * O yüzden iki katman birlikte:
 *   1. Karar yoksa konteyner script'i sayfaya HİÇ girmez.
 *   2. Karar varsa bile Consent Mode varsayılanı `denied` olarak
 *      konteynerden ÖNCE basılır ve kabul edilen eksenler açılır — böylece
 *      konteynerin içine sonradan eklenen bir etiket de sinyale uyar.
 *
 * İkinci katman olmadan, sahibin GTM arayüzünden ekleyeceği yeni bir etiket
 * bu kapının dışında kalırdı; bu kapının bütün değeri o gün kaybolurdu.
 *
 * ═══ BU SINIF NEYİ KAPSAMAZ ═══
 *
 * `analytics_events` tablosu — ürünün KENDİ birinci-taraf defteri — bu
 * kapının konusu değildir ve bu pakette davranışı DEĞİŞTİRİLMEDİ. O veri
 * üçüncü tarafa akmaz, sahibin kendi ürününün parçasıdır ve takma
 * kimliklidir (ziyaretçi anahtarı adresi gizler, günlük döner).
 *
 * Birinci-taraf ölçümün de onaya bağlanıp bağlanmayacağı bir HUKUK
 * kararıdır, mühendislik kararı değil — ve bu sınıf onu sessizce vermez.
 */
final class MeasurementConsent
{
    /** Kararın saklandığı çerez. Sunucu okur, çünkü kapı sunucuda. */
    public const COOKIE = 'zabuno_measurement_consent';

    /**
     * Kararın ömrü.
     *
     * Sonsuz değil: bir yıl sonra soru yeniden sorulur. "Bir kez onayladı,
     * ömür boyu onayladı" saymak, onayı bir imzaya çevirirdi.
     */
    public const LIFETIME_DAYS = 365;

    private function __construct(private readonly ?bool $granted) {}

    /**
     * İsteğin taşıdığı karar.
     *
     * ÜÇ HÂL VAR, İKİ DEĞİL: kabul · ret · **henüz sorulmadı**. Sorulmamışı
     * "ret" saymak kapıyı doğru kurar ama ekranı yanlış çizer — kullanıcıya
     * bir daha sorulmazdı. Tanınmayan bir çerez değeri de "sorulmadı"
     * sayılır: bozuk bir değere anlam yüklemek, kullanıcının vermediği bir
     * kararı uydurmaktır.
     */
    public static function fromRequest(Request $request): self
    {
        $value = $request->cookie(self::COOKIE);

        return new self(match ($value) {
            'granted' => true,
            'denied' => false,
            default => null,
        });
    }

    public static function granted(): self
    {
        return new self(true);
    }

    public static function denied(): self
    {
        return new self(false);
    }

    /** Karar verildi mi? Verilmediyse hiçbir üçüncü taraf script'i yüklenmez. */
    public function isDecided(): bool
    {
        return $this->granted !== null;
    }

    public function isGranted(): bool
    {
        return $this->granted === true;
    }

    /**
     * Konteyner yüklenebilir mi?
     *
     * Yalnız AÇIK bir kabulle. Ret de bir karardır ve konteyneri açmaz —
     * "karar verildi" ile "kabul edildi" ayrı iki şeydir ve bu ayrımı
     * kaybetmek, reddeden kullanıcıyı ölçmek olurdu.
     */
    public function allowsContainer(): bool
    {
        return $this->isGranted();
    }

    /**
     * Consent Mode varsayılanı — konteynerden ÖNCE basılır.
     *
     * Reddedilen eksen `denied` kalır. `security_storage` her zaman
     * `granted`: o eksen güvenlik içindir (kötüye kullanım tespiti) ve
     * ölçüm değildir; reddedilmesi ürünü savunmasız bırakırdı.
     *
     * @return array<string, string>
     */
    public function consentModeDefaults(): array
    {
        $state = $this->isGranted() ? 'granted' : 'denied';

        return [
            'ad_storage' => $state,
            'ad_user_data' => $state,
            'ad_personalization' => $state,
            'analytics_storage' => $state,
            'functionality_storage' => $state,
            'personalization_storage' => $state,
            'security_storage' => 'granted',
        ];
    }
}
