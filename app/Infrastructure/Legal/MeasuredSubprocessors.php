<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal;

use App\Application\Legal\Port\SubprocessorRegistryPort;
use App\Application\Platform\Port\PlatformCredentialAdminPort;
use App\Domain\Legal\Subprocessor;
use App\Domain\Legal\SubprocessorInventory;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Domain\Platform\Credential\CredentialStatus;
use Throwable;

/**
 * Alt işleyen listesi — ELLE YAZILMAZ, ÖLÇÜLÜR (FF-228, `docs/140` §3).
 *
 * ═══ NEDEN TÜRETİLİYOR ═══
 *
 * Bir alt işleyen listesi, yazıldığı gün doğru olan ve ertesi gün sessizce
 * yanlışa dönen bir belgedir. Sahibi kasadan bir AI sağlayıcısı açtığında ya
 * da ölçüm aracını devreye aldığında, elle yazılmış bir liste hiçbir uyarı
 * vermeden eskir — ve bir zincirin hukukçusuna verilen sözün tam olarak
 * yanlış olduğu yer orasıdır.
 *
 * Bu yüzden liste ÜÇ ölçülen kaynaktan üretilir ve DPA sayfası her
 * çizildiğinde yeniden okunur:
 *
 * 1. **Barındırma** — `config/legal.php#hosting`. Verinin fiziksel olarak
 *    durduğu yer; her dağıtımda vardır ve hiçbir koşula bağlı değildir.
 * 2. **Kimlik kasası** — `PlatformCredentialAdminPort::all()`. Kasada etkin
 *    bir kaydı OLMAYAN bir sağlayıcı bugün veri işlemiyordur ve listede yer
 *    almaz. Kasa boşken sunucu `.env`'i devrede olabilir (`docs/93` FF-36
 *    aktarımı), o yüzden Mailgun ve Iyzico için env yedeği de sayılır.
 * 3. **Ölçüm** — `config/analytics.php`. GTM kap kimliği yoksa tek bir
 *    script yüklenmez; hedef kapalıysa CSP zaten engeller. İkisi de açıkken
 *    araç yalnız ziyaretçi KABUL ETTİKTEN sonra çalışır ve metin bunu söyler.
 *
 * ═══ SIR OKUNMAZ ═══
 *
 * Buraya `CredentialResolverPort` DEĞİL, `PlatformCredentialAdminPort`
 * enjekte edilir: bu port bir sırrı geri okuyamaz, yalnız "var mı/etkin mi"
 * söyler (`PlatformCredentialAdminPort` doktrini). Herkese açık bir sayfayı
 * çizen kod, bir anahtarın değerine fiziksel olarak erişemez.
 *
 * ═══ YENİ BİR SAĞLAYICI EKLENDİĞİNDE ═══
 *
 * `describe()` bir `match` ve varsayılanı YOKTUR. `CredentialProvider`'a bir
 * case eklendiği anda buradan geçen çağrı `UnhandledMatchError` fırlatır ve
 * `SubprocessorRegistryTest` bunu her sağlayıcı için ölçer. Yani belge
 * eskimez: eskiyeceği gün CI kırılır (`docs/140` §3).
 */
final class MeasuredSubprocessors implements SubprocessorRegistryPort
{
    /**
     * Üçüncü tarafın veriyi nerede işlediği bu üründen ÖLÇÜLEMEZ.
     *
     * Bir sağlayıcının hangi ülkedeki hangi veri merkezini kullandığı onun
     * kendi sözleşmesinde yazar; buradan "ABD" ya da "AB" yazmak, ölçülmemiş
     * bir olguyu sözleşmeye taşımak olurdu. Ölçebildiğimiz tek konum,
     * verinin BİZDE durduğu yerdir — ve o, barındırma satırındadır.
     */
    private const LOCATION_NOT_MEASURED = 'Determined by that provider under its own terms; this service does not measure where the provider stores data.';

    /**
     * `config/analytics.php#destinations` anahtarı → aracın ADI.
     *
     * Genel (`public`) çünkü bir KAPI onu okur: `SubprocessorRegistryTest`,
     * yapılandırmadaki her hedefin burada bir adı olduğunu ölçer. GTM
     * konteynerine yeni bir araç eklemek CSP kaynağı eklemeyi gerektirir
     * (`config/analytics.php`), ve o gün bu eşleme de eklenmezse belge
     * sessizce eksik kalırdı.
     *
     * @var array<string, string>
     */
    public const MEASUREMENT_TOOL_NAMES = [
        'ga4' => 'Google Analytics 4',
        'yandex_metrica' => 'Yandex Metrica',
        'hotjar' => 'Hotjar',
    ];

    public function __construct(private readonly PlatformCredentialAdminPort $vault) {}

    public function inventory(string $locale = 'en'): SubprocessorInventory
    {
        $active = [$this->hosting($locale)];
        $vaultUnreadable = false;

        try {
            $statuses = $this->vault->all();
        } catch (Throwable) {
            /*
                Kasa okunamadı. BOŞ LİSTE DÖNMEK YALANDIR: "hiçbir sağlayıcı
                yok" ile "bakamadık" aynı cümle değildir ve bir veri işleme
                sözleşmesinde ikisini karıştırmak en pahalı hatadır.
            */
            $statuses = [];
            $vaultUnreadable = true;
        }

        $configured = [];

        foreach ($statuses as $status) {
            if ($status instanceof CredentialStatus && $status->configured) {
                $configured[$status->provider->value] = true;
            }
        }

        foreach (CredentialProvider::cases() as $provider) {
            if (isset($configured[$provider->value]) || $this->hasEnvFallback($provider)) {
                $active[] = $this->describe($provider, $locale);
            }
        }

        foreach ($this->measurementTools($locale) as $tool) {
            $active[] = $tool;
        }

        return new SubprocessorInventory($active, $vaultUnreadable);
    }

    /**
     * Barındırma — verinin fiziksel olarak durduğu yer.
     *
     * Değer yapılandırmadan gelir (`config/legal.php#hosting`) çünkü bu
     * yazılım tek bir kuruluma ait değil: kendi sunucusuna kuran biri kendi
     * sağlayıcısını yazar. Varsayılan, BU ürünün üretim dağıtımının ölçülmüş
     * hâlidir (`docs/42`, `docs/43`), uydurma değil.
     */
    private function hosting(string $locale): Subprocessor
    {
        return new Subprocessor(
            name: $this->hostingValue('provider', $locale),
            role: ($locale === 'tr' ? 'Barındırma: uygulamanın, veritabanının ve yüklenen dosyaların bulunduğu sanal sunucuyu işletir.' : 'Hosting: runs the virtual server on which the application, the database and the uploaded files live.'),
            data: ($locale === 'tr' ? 'Hizmetin sakladığı her şey — hesap verileri, çalışma alanı ve menü içeriği, yüklenen görseller, günlükler ve veritabanı yedekleri.' : 'Everything the service stores — account data, workspace and menu content, uploaded images, logs and database backups.'),
            location: $this->hostingValue('location', $locale),
        );
    }

    private function hostingValue(string $key, string $locale): string
    {
        $value = config('legal.hosting.'.$key);

        return is_string($value) && trim($value) !== '' ? trim($value) : ($locale === 'tr' ? 'henüz belirtilmedi' : 'not yet provided');
    }

    /**
     * Kasada anahtarı olmayan ama sunucu `.env`'inden çalışabilen sağlayıcı.
     *
     * Yalnız Mailgun ve Iyzico'nun env yedeği vardır ve bu, kasanın kendi
     * kararıdır (`EloquentPlatformCredentialStore::envValues`). Buradaki
     * kontrol o listeyi TEKRAR ETMEZ, aynı yapılandırma anahtarlarına bakar:
     * ikinci bir liste, birincisinden bir gün ayrılırdı.
     */
    private function hasEnvFallback(CredentialProvider $provider): bool
    {
        $keys = match ($provider) {
            CredentialProvider::Mailgun => ['services.mailgun.domain', 'services.mailgun.secret'],
            CredentialProvider::Iyzico => ['services.iyzico.sandbox.api_key', 'services.iyzico.sandbox.secret_key'],
            default => [],
        };

        if ($keys === []) {
            return false;
        }

        foreach ($keys as $key) {
            $value = config($key);

            if (! is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Bir sağlayıcının DPA ekindeki satırı.
     *
     * `match` VARSAYILANSIZDIR ve öyle kalmalı: yeni bir sağlayıcı eklendiği
     * gün bu metot patlar, test kırılır ve belge tarif edilmeden yayına
     * çıkamaz.
     */
    private function describe(CredentialProvider $provider, string $locale): Subprocessor
    {
        return match ($provider) {
            CredentialProvider::Mailgun => new Subprocessor(
                name: 'Mailgun',
                role: ($locale === 'tr' ? 'E-posta iletimi: adres doğrulama, ekip daveti, bildirim ve iletişim formu alındı mesajlarını gönderir.' : 'E-mail delivery: sends address-verification, team-invitation, notification and contact-form receipt messages.'),
                data: ($locale === 'tr' ? 'Alıcının e-posta adresi, gönderici adresi ve gönderilen mesajın içeriği.' : 'The recipient e-mail address, the sender address and the content of the message that is sent.'),
                location: $locale === 'tr' ? 'Sağlayıcının kendi koşulları uyarınca belirlenir; bu hizmet sağlayıcının verileri nerede sakladığını ölçmez.' : self::LOCATION_NOT_MEASURED,
            ),
            CredentialProvider::Iyzico => new Subprocessor(
                name: 'iyzico',
                role: ($locale === 'tr' ? 'Ödeme hizmeti sağlayıcısı: ücretli planın ödemesini tahsil eder ve hizmete onayını iletir.' : 'Payment service provider: collects the payment for a paid plan and confirms it back to the service.'),
                data: ($locale === 'tr' ? 'Siparişle gönderilen faturalandırma bilgileri ve ödeme sonucu. Kart bilgileri sağlayıcının kendi sistemlerine girilir ve bu hizmete hiçbir zaman ulaşmaz.' : 'The billing details submitted with the order and the result of the payment. Card details are entered on the provider\'s own systems and never reach this service.'),
                location: $locale === 'tr' ? 'Sağlayıcının kendi koşulları uyarınca belirlenir; bu hizmet sağlayıcının verileri nerede sakladığını ölçmez.' : self::LOCATION_NOT_MEASURED,
            ),
            /*
                ÖLÇÜM KONTEYNERİ TARAYICIDA ÇALIŞIR, SUNUCUDA DEĞİL.

                Google Tag Manager bir kap'tır: ziyaretçinin tarayıcısına
                yükleniyor ve içine kurulmuş araçları (GA4, Yandex Metrica)
                orada çalıştırıyor. Bu servisin sunucusu ziyaretçi verisini
                Google'a GÖNDERMİYOR — ziyaretçinin kendi tarayıcısı
                gönderiyor. Alt işleyen listesinde yer alması bu ayrımı
                gizlemek için değil, tam tersine yazmak içindir: müşteri
                kimin veri gördüğünü bilmeli.

                VE YALNIZ ONAYDAN SONRA: konteyner, ziyaretçi çerez
                tercihinde ölçüme izin vermeden hiç yüklenmiyor
                (`docs/126`, `docs/135`). Onay verilmemişse bu satırın
                karşılığı olan hiçbir istek çıkmaz.
            */
            CredentialProvider::GoogleTagManager => new Subprocessor(
                name: ($locale === 'tr' ? 'Google (Tag Manager ve içinde yapılandırılmış araçlar)' : 'Google (Tag Manager, and the tools configured inside it)'),
                role: ($locale === 'tr' ? 'Ziyaretçinin tarayıcısına yalnızca çerez tercihinde ölçüme izin vermesinden sonra yüklenen ölçüm kapsayıcısı. Kapsayıcı, işletmecinin içinde yapılandırdığı analiz araçlarını çalıştırır.' : 'Measurement container loaded in the visitor\'s browser, and only after the visitor allows measurement in the cookie choice. The container runs the analytics tools the operator has configured inside it.'),
                data: ($locale === 'tr' ? 'Ziyaretçinin tarayıcısının kapsayıcıdaki araçlara gönderdiği veriler: görüntülenen sayfalar, gelinen adres, ağ adresinden türetilen yaklaşık konum, cihaz ve tarayıcı özellikleri. Hizmetin kendi sunucuları Google’a ziyaretçi verisi göndermez; ziyaretçinin tarayıcısı gönderir.' : 'What the visitor\'s browser sends to the tools inside the container: pages viewed, the address they arrived from, approximate location derived from the network address, and device and browser characteristics. This service\'s own servers do not send visitor data to Google; the visitor\'s browser does.'),
                location: $locale === 'tr' ? 'Sağlayıcının kendi koşulları uyarınca belirlenir; bu hizmet sağlayıcının verileri nerede sakladığını ölçmez.' : self::LOCATION_NOT_MEASURED,
            ),
            CredentialProvider::OpenAi => $this->aiProvider('OpenAI', $locale),
            CredentialProvider::Gemini => $this->aiProvider('Google (Gemini API)', $locale),
            CredentialProvider::Anthropic => $this->aiProvider('Anthropic', $locale),
            CredentialProvider::Kimi => $this->aiProvider('Moonshot AI (Kimi)', $locale),
            /*
                ÖZEL UÇ NOKTA BİR ŞİRKET ADI DEĞİL, BİR ADRESTİR. Kendi
                sunucusunu (Qwen/vLLM/Ollama) yazan bir kurulumda alt işleyen
                sağlayıcı değil, o sunucuyu işleten taraftır — ve o taraf
                çoğu zaman müşterinin kendisidir. Adres bir sır değildir ama
                yine de yazılmaz: uç nokta adresi bir altyapı olgusudur ve
                herkese açık bir sayfada duyurulması gereken bir şey değildir.
            */
            CredentialProvider::CustomEndpoint => new Subprocessor(
                name: ($locale === 'tr' ? 'Kendi sunucusunda barındırılan yapay zekâ uç noktası' : 'Self-hosted AI endpoint'),
                role: ($locale === 'tr' ? 'Adı belirtilmiş bir sağlayıcı yerine bu kurulumun işletmecisinin yapılandırdığı uç noktada çalışan yapay zekâ destekli menü aktarımı.' : 'AI-assisted menu import, running against an endpoint configured by the operator of this deployment rather than a named vendor.'),
                data: ($locale === 'tr' ? 'Bu özelliğe gönderilen menü metni veya menü fotoğrafı.' : 'The menu text or menu photograph submitted to that feature.'),
                location: ($locale === 'tr' ? 'Bu kurulumun işletmecisinin yapılandırdığı sunucu; hangisi olduğunu işletmeciye sorun.' : 'The server the operator of this deployment configured; ask the operator which one it is.'),
            ),
        };
    }

    private function aiProvider(string $name, string $locale): Subprocessor
    {
        return new Subprocessor(
            name: $name,
            role: ($locale === 'tr' ? 'Yapay zekâ destekli menü aktarımı: bu özelliğe gönderdiğiniz menü metnini veya fotoğrafını okur ve taslak menü döndürür.' : 'AI-assisted menu import: reads the menu text or photograph you submit to that feature and returns a draft menu.'),
            data: ($locale === 'tr' ? 'Yalnızca bu özelliğe gönderilenler — menü metni veya fotoğraf. Hesap, misafir ve ödeme verileri gönderilmez.' : 'Only what is submitted to that feature — the menu text or the photograph. Account data, guest data and payment data are not sent.'),
            location: $locale === 'tr' ? 'Sağlayıcının kendi koşulları uyarınca belirlenir; bu hizmet sağlayıcının verileri nerede sakladığını ölçmez.' : self::LOCATION_NOT_MEASURED,
        );
    }

    /**
     * Ölçüm araçları — YALNIZ onaydan sonra ve yalnız yapılandırılmışsa.
     *
     * GTM kap kimliği boşken hiçbir script yüklenmez; hedef kapalıyken CSP
     * isteği engeller (`config/analytics.php`). İkisi de açıkken bile araç
     * ancak ziyaretçi çerez şeridinde kabul ettikten sonra çalışır.
     *
     * @return list<Subprocessor>
     */
    private function measurementTools(string $locale): array
    {
        $container = config('analytics.gtm_container_id');

        if (! is_string($container) || trim($container) === '') {
            return [];
        }

        $tools = [new Subprocessor(
            name: 'Google Tag Manager',
            role: ($locale === 'tr' ? 'Aşağıdaki ölçüm araçlarını yalnızca ziyaretçi ölçüm çerezlerini kabul ettikten sonra yükler.' : 'Loads the measurement tools listed below, and only after a visitor accepts measurement cookies.'),
            data: ($locale === 'tr' ? 'Hangi sayfanın açıldığı ve dönüşüm gerçekleşip gerçekleşmediği. Form içerikleri, e-posta adresleri ve adlar gönderilmez.' : 'Which page was opened and whether a conversion happened. Form contents, e-mail addresses and names are not sent.'),
            location: $locale === 'tr' ? 'Sağlayıcının kendi koşulları uyarınca belirlenir; bu hizmet sağlayıcının verileri nerede sakladığını ölçmez.' : self::LOCATION_NOT_MEASURED,
        )];

        $names = self::MEASUREMENT_TOOL_NAMES;

        /** @var array<string, mixed> $destinations */
        $destinations = (array) config('analytics.destinations', []);

        foreach ($names as $key => $name) {
            if (($destinations[$key] ?? false) !== true) {
                continue;
            }

            $tools[] = new Subprocessor(
                name: $name,
                role: ($locale === 'tr' ? 'Yalnızca ziyaretçi ölçüm çerezlerini kabul ettikten sonra Google Tag Manager üzerinden yüklenen ölçüm.' : 'Measurement, loaded through Google Tag Manager only after a visitor accepts measurement cookies.'),
                data: ($locale === 'tr' ? 'Aracın kendi çerezleri kapsamında, herkese açık sitedeki ve yayımlanmış menülerdeki sayfa görüntülemeleri ve olaylar.' : 'Page views and events on the public site and on published menus, under that tool\'s own cookies.'),
                location: $locale === 'tr' ? 'Sağlayıcının kendi koşulları uyarınca belirlenir; bu hizmet sağlayıcının verileri nerede sakladığını ölçmez.' : self::LOCATION_NOT_MEASURED,
            );
        }

        return $tools;
    }
}
