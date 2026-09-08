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

    public function inventory(): SubprocessorInventory
    {
        $active = [$this->hosting()];
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
                $active[] = $this->describe($provider);
            }
        }

        foreach ($this->measurementTools() as $tool) {
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
    private function hosting(): Subprocessor
    {
        return new Subprocessor(
            name: $this->hostingValue('provider'),
            role: 'Hosting: runs the virtual server on which the application, the database and the uploaded files live.',
            data: 'Everything the service stores — account data, workspace and menu content, uploaded images, logs and database backups.',
            location: $this->hostingValue('location'),
        );
    }

    private function hostingValue(string $key): string
    {
        $value = config('legal.hosting.'.$key);

        return is_string($value) && trim($value) !== '' ? trim($value) : 'not yet provided';
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
    private function describe(CredentialProvider $provider): Subprocessor
    {
        return match ($provider) {
            CredentialProvider::Mailgun => new Subprocessor(
                name: 'Mailgun',
                role: 'E-mail delivery: sends address-verification, team-invitation, notification and contact-form receipt messages.',
                data: 'The recipient e-mail address, the sender address and the content of the message that is sent.',
                location: self::LOCATION_NOT_MEASURED,
            ),
            CredentialProvider::Iyzico => new Subprocessor(
                name: 'iyzico',
                role: 'Payment service provider: collects the payment for a paid plan and confirms it back to the service.',
                data: 'The billing details submitted with the order and the result of the payment. Card details are entered on the provider\'s own systems and never reach this service.',
                location: self::LOCATION_NOT_MEASURED,
            ),
            CredentialProvider::OpenAi => $this->aiProvider('OpenAI'),
            CredentialProvider::Gemini => $this->aiProvider('Google (Gemini API)'),
            CredentialProvider::Anthropic => $this->aiProvider('Anthropic'),
            CredentialProvider::Kimi => $this->aiProvider('Moonshot AI (Kimi)'),
            /*
                ÖZEL UÇ NOKTA BİR ŞİRKET ADI DEĞİL, BİR ADRESTİR. Kendi
                sunucusunu (Qwen/vLLM/Ollama) yazan bir kurulumda alt işleyen
                sağlayıcı değil, o sunucuyu işleten taraftır — ve o taraf
                çoğu zaman müşterinin kendisidir. Adres bir sır değildir ama
                yine de yazılmaz: uç nokta adresi bir altyapı olgusudur ve
                herkese açık bir sayfada duyurulması gereken bir şey değildir.
            */
            CredentialProvider::CustomEndpoint => new Subprocessor(
                name: 'Self-hosted AI endpoint',
                role: 'AI-assisted menu import, running against an endpoint configured by the operator of this deployment rather than a named vendor.',
                data: 'The menu text or menu photograph submitted to that feature.',
                location: 'The server the operator of this deployment configured; ask the operator which one it is.',
            ),
        };
    }

    private function aiProvider(string $name): Subprocessor
    {
        return new Subprocessor(
            name: $name,
            role: 'AI-assisted menu import: reads the menu text or photograph you submit to that feature and returns a draft menu.',
            data: 'Only what is submitted to that feature — the menu text or the photograph. Account data, guest data and payment data are not sent.',
            location: self::LOCATION_NOT_MEASURED,
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
    private function measurementTools(): array
    {
        $container = config('analytics.gtm_container_id');

        if (! is_string($container) || trim($container) === '') {
            return [];
        }

        $tools = [new Subprocessor(
            name: 'Google Tag Manager',
            role: 'Loads the measurement tools listed below, and only after a visitor accepts measurement cookies.',
            data: 'Which page was opened and whether a conversion happened. Form contents, e-mail addresses and names are not sent.',
            location: self::LOCATION_NOT_MEASURED,
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
                role: 'Measurement, loaded through Google Tag Manager only after a visitor accepts measurement cookies.',
                data: 'Page views and events on the public site and on published menus, under that tool\'s own cookies.',
                location: self::LOCATION_NOT_MEASURED,
            );
        }

        return $tools;
    }
}
