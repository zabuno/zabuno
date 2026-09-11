<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Application\Mail\Port\MailTransportSelectorPort;
use App\Application\Platform\Port\CredentialResolverPort;
use App\Domain\Platform\Credential\CredentialProvider;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Kasadan (yoksa env'den) Mailgun kimliğini çözer ve sürücüyü seçer.
 *
 * Resolver zaten KASA > env önceliğini uygular; burada yalnız çözülen değeri
 * `services.mailgun.*` config'ine yazıp `mailgun` sürücüsünü seçiyoruz.
 *
 * ÜÇ AYRI DURUM, ÜÇ AYRI CEVAP — VE ÜÇÜ BİRBİRİNE KARIŞMAZ.
 *
 * 1. KİMLİK YOK. Zorunlu alanlar (domain + secret) hiçbir kaynaktan
 *    gelmiyorsa Mailgun hiç yapılandırılmamıştır. Varsayılan sürücü
 *    (`mail.default`) kalır ve hiçbir arıza üretilmez: kimlik yokken
 *    gönderici uydurmayız, ama ortada düzeltilecek bir yanlış da yoktur.
 *    ÜRETİMDE BUNUN BİR ŞARTI VARDIR: o varsayılan gerçekten bir yere
 *    gönderiyor olmalıdır (bkz. 3).
 *
 * 2. KİMLİK VAR, UÇ NOKTA TAŞINAMIYOR. Bu bir yapılandırma ARIZASIDIR ve
 *    sessizce yedeğe dönmek, sahibin bu paketi doğuran şikâyetini geri
 *    getirirdi: `mail.default` üretimde `log`tur, yani e-posta
 *    "gönderilmiş" sayılır, bir dosyaya yazılır ve kimseye ulaşmaz. Panelde
 *    her kutu dolu görünürken sıfırlama e-postası yine gelmez. Bu yüzden
 *    burada sessiz bir yedek YOKTUR: çağıran taraf bir istisna alır ve
 *    "gönderemedim" diyebilir.
 *
 * 3. ÜRETİMDE KİMLİK YOK VE VARSAYILAN DA GÖNDERMİYOR. `log`, `array`,
 *    `null` ya da hiç tanımlanmamış bir gönderici e-postayı bir dosyaya,
 *    belleğe ya da hiçliğe yazar; çağıran taraf "gönderildi" sanır.
 *    Böyle bir üretim kurulumunda ekranda "bağlantı gönderildi" görünür,
 *    posta kutusuna hiçbir şey düşmez ve hiçbir yerde hata belirmez.
 *    Üretimde bu da bir ARIZADIR ve sessizce yutulmaz; yerel/test
 *    ortamında ise posta sağlayıcısı zorunlu değildir ve bu dal hiç
 *    işlemez.
 */
final readonly class VaultMailTransportSelector implements MailTransportSelectorPort
{
    /**
     * "Gönderildi" der ama hiçbir yere göndermez: mesajı bir dosyaya
     * (`log`), belleğe (`array`) ya da hiçliğe (`null`) yazar. Üretimde bu
     * sürücüler bir yedek DEĞİLDİR.
     */
    private const SILENT_TRANSPORTS = ['log', 'array', 'null'];

    public function __construct(
        private CredentialResolverPort $resolver,
        private ConfigRepository $config,
        private MailgunEndpointNormalizer $endpoints,
    ) {}

    public function select(): string
    {
        $creds = $this->resolver->resolve(CredentialProvider::Mailgun);

        if (($creds['domain'] ?? '') === '' || ($creds['secret'] ?? '') === '') {
            $default = (string) $this->config->get('mail.default');

            /*
                ÜRETİMDE YEDEK, ANCAK GERÇEKTEN GÖNDERİYORSA YEDEKTİR.

                Kimlik girilmemişken düzeltilecek bir yazım yoktur — ama
                giden bir yol da olmayabilir. `log` mesajı sunucudaki bir
                dosyaya, `array` belleğe yazar; ikisi de başarıyla döner ve
                kullanıcı beklemeye devam eder. Kendi SMTP sunucusundan
                gönderen bir kurulum ise bu paketten etkilenmez: orada
                e-posta gerçekten çıkar, dolayısıyla yedek korunur.

                Yerel ve test ortamı KAPSAM DIŞIDIR: geliştiricinin
                makinesinde bir posta sağlayıcısı şart koşulamaz.
            */
            if ($this->isProduction() && ! $this->carriesOutboundMail($default)) {
                /*
                    MESAJ ARINDIRILMIŞTIR. Ne gönderici adı ne de yarım
                    girilmiş bir sır tekrar edilir; bu metin günlüğe ve
                    hata izleyicisine düşer. Operatörün ihtiyacı olan tek
                    bilgi HANGİ ayarın eksik olduğudur.
                */
                throw new RuntimeException(
                    'Üretimde giden posta yolu yapılandırılmamış '
                    .'(sağlayıcı: '.CredentialProvider::Mailgun->value.', sebep: no-outbound-transport-configured). '
                    .'E-posta gönderilmedi; Mailgun kimliği superadmin panelinden girilmeli '
                    .'ya da `mail.default` gerçekten gönderen bir göndericiye ayarlanmalıdır.'
                );
            }

            return $default;
        }

        $endpoint = $this->endpoints->normalize($creds['endpoint'] ?? null);

        if ($endpoint === null) {
            /*
                UÇ NOKTA TAŞINAMIYOR: NE MAILGUN SEÇİLİR, NE DE SESSİZ YEDEK.

                Kaydedilmiş değer taşıyıcının host alanına sığmıyor (bkz.
                `MailgunEndpointNormalizer`). Onu budayıp "herhalde bunu
                kastetti" demek, API anahtarını kimsenin seçmediği bir
                sunucuya göndermek olurdu; bu yüzden Mailgun seçilmez ve
                `services.mailgun.*` hiç yazılmaz — kasa sırrı yüklenmez.

                Ama `mail.default`'a dönmek de bir cevap DEĞİLDİR. Üretimde
                o değer `log`tur: mesaj bir dosyaya yazılır, çağıran taraf
                "gönderildi" sanır ve kullanıcı beklemeye devam eder.
                Kimliğin girilmiş olduğu bir kurulumda tek dürüst sonuç
                budur: gönderemedim.

                MESAJ ARINDIRILMIŞTIR. Ne kaydedilmiş uç nokta değeri ne de
                herhangi bir sır tekrar edilir — reddedilme sebeplerinden
                biri tam olarak "değerin içinde kimlik var" olabilir ve bu
                metin günlüğe, hata izleyicisine, hatta hata ayıklama açık
                bir ortamda ekrana düşer. Operatörün ihtiyacı olan tek bilgi
                HANGİ AYARIN yanlış olduğudur, değerin kendisi değil.
            */
            throw new RuntimeException(
                'Kayıtlı Mailgun uç noktası taşıyıcının host alanına taşınamıyor '
                .'(sağlayıcı: '.CredentialProvider::Mailgun->value.', sebep: endpoint-not-carryable-as-host). '
                .'E-posta gönderilmedi; uç nokta ayarı superadmin panelinden düzeltilmelidir.'
            );
        }

        $changed = $this->config->get('services.mailgun.domain') !== $creds['domain']
            || $this->config->get('services.mailgun.secret') !== $creds['secret']
            || $this->config->get('services.mailgun.endpoint') !== $endpoint;

        $this->config->set('services.mailgun.domain', $creds['domain']);
        $this->config->set('services.mailgun.secret', $creds['secret']);
        $this->config->set('services.mailgun.endpoint', $endpoint);

        if ($changed) {
            /*
                ÖNBELLEKTEKİ TAŞIYICI, YENİ KASA DEĞERİNİ YENEBİLİR.

                `MailManager` çözdüğü her göndericiyi adına göre saklar ve
                taşıyıcıyı YALNIZ ilk kurulumda config'den okur. Tek istekte
                ömrü biten PHP-FPM'de bu görünmez; kalıcı bir süreçte
                (queue worker, Octane) görünür ve tam olarak sahibin
                yaşadığı şekle bürünür: superadmin panelden anahtarı
                düzeltir, ekranda kaydedildi yazar, e-posta hâlâ eski —
                artık geçersiz — anahtarla çıkmayı sürdürür.

                Bu yüzden değer değiştiğinde önbellekteki `mailgun`
                göndericisi düşürülür; bir sonraki kullanım taşıyıcıyı
                az önce yazılan değerlerle yeniden kurar. Değişmediğinde
                düşürülmez: gereksiz yeniden kurulum, davranışı
                iyileştirmeden her gönderime maliyet eklerdi.
            */
            Mail::purge('mailgun');
        }

        return 'mailgun';
    }

    /**
     * Ortam adı config'ten okunur — `app()->environment()` ile aynı kaynak.
     *
     * Çerçeve önyüklemede ortamı `app.env`'den belirler; buradan okumak hem
     * aynı gerçeği verir hem de bu sınıfın kapsayıcıya bağımlı olmamasını
     * sürdürür. STAGING KASTEN DIŞARIDADIR: bu paketin kapsamı üretimdir.
     */
    private function isProduction(): bool
    {
        return $this->config->get('app.env') === 'production';
    }

    /**
     * Bu gönderici e-postayı gerçekten dışarı çıkarır mı?
     *
     * ADA DEĞİL, SÜRÜCÜYE BAKILIR. `mail.default` bir sürücü adı değil bir
     * GÖNDERİCİ adıdır; operatör ona `bildirim` diyebilir ve arkasına `log`
     * koyabilir. Bu yüzden ad bir kez `mail.mailers.*` üzerinden çözülür ve
     * kararı sürücü verir.
     *
     * ÇÖZÜM TEK ADIMDIR, BİR GRAF DEĞİL. `failover`/`roundrobin` gibi başka
     * göndericilere işaret eden sürücüler burada "gönderiyor" sayılır:
     * listelerini dolaşıp "hepsi sessiz mi?" diye karar vermek ayrı bir
     * kapsamdır ve bu paket o kararı vermez. Bilinen sessiz sürücüler dar
     * ve açık bir listedir; tanınmayan her sürücü gönderiyor kabul edilir,
     * çünkü bu dosya bir izin listesi değildir.
     */
    private function carriesOutboundMail(string $mailer): bool
    {
        if ($mailer === '') {
            return false;
        }

        $configured = $this->config->get('mail.mailers.'.$mailer);
        $transport = is_array($configured) ? ($configured['transport'] ?? null) : $configured;

        if (! is_string($transport) || $transport === '') {
            // Karşılığı olmayan ya da sürücüsü yazılmamış bir gönderici
            // adı: kurulamaz, dolayısıyla hiçbir yere göndermez.
            return false;
        }

        return ! in_array(strtolower($transport), self::SILENT_TRANSPORTS, true);
    }
}
