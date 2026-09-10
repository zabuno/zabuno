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
 * İKİ AYRI DURUM, İKİ AYRI CEVAP — VE İKİSİ BİRBİRİNE KARIŞMAZ.
 *
 * 1. KİMLİK YOK. Zorunlu alanlar (domain + secret) hiçbir kaynaktan
 *    gelmiyorsa Mailgun hiç yapılandırılmamıştır. Varsayılan sürücü
 *    (`mail.default`) kalır ve hiçbir arıza üretilmez: kimlik yokken
 *    gönderici uydurmayız, ama ortada düzeltilecek bir yanlış da yoktur.
 *
 * 2. KİMLİK VAR, UÇ NOKTA TAŞINAMIYOR. Bu bir yapılandırma ARIZASIDIR ve
 *    sessizce yedeğe dönmek, sahibin bu paketi doğuran şikâyetini geri
 *    getirirdi: `mail.default` üretimde `log`tur, yani e-posta
 *    "gönderilmiş" sayılır, bir dosyaya yazılır ve kimseye ulaşmaz. Panelde
 *    her kutu dolu görünürken sıfırlama e-postası yine gelmez. Bu yüzden
 *    burada sessiz bir yedek YOKTUR: çağıran taraf bir istisna alır ve
 *    "gönderemedim" diyebilir.
 */
final readonly class VaultMailTransportSelector implements MailTransportSelectorPort
{
    public function __construct(
        private CredentialResolverPort $resolver,
        private ConfigRepository $config,
        private MailgunEndpointNormalizer $endpoints,
    ) {}

    public function select(): string
    {
        $creds = $this->resolver->resolve(CredentialProvider::Mailgun);

        if (($creds['domain'] ?? '') === '' || ($creds['secret'] ?? '') === '') {
            return (string) $this->config->get('mail.default');
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
}
