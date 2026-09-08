<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal\Documents\Turkish;

use App\Domain\Legal\LegalDocument;
use App\Domain\Legal\LegalSection;

final class CookiePolicy
{
    public static function document(): LegalDocument
    {
        return new LegalDocument(
            language: 'tr',
            key: 'cookies',
            version: '0.1',
            effectiveDate: '2026-09-06',
            title: 'Çerez Politikası',
            summary: 'Zabuno\'nun hangi çerezleri yerleştirdiği, her birinin ne yaptığı, ne kadar süre kaldığı ve ölçüm çerezleriyle ilgili tercihinizi nasıl değiştirebileceğiniz.',
            sections: [
                new LegalSection('Çerez nedir?', [
                    'Çerez, bir web sitesinin tarayıcınıza kaydettiği ve sonraki isteklerde tekrar okuduğu küçük bir metin kaydıdır. Bazı çerezler sitenin çalışması için gereklidir; diğerleri yalnızca ölçüm için kullanılır ve üçüncü taraf araçlarca yerleştirilir.',
                    'Bu politika {company.legal_name} tarafından yayımlanır ve Zabuno web sitesini, çalışma alanı uygulamasını ve yayımlanmış menü sayfalarını kapsar.',
                ]),
                new LegalSection('Hizmetin kendisinin yerleştirdiği çerezler', [
                    'Oturum çerezi (adı "-session" ile biter): oturumunuzu açık tutar ve bulunduğunuz sayfanın durumunu hatırlar. Ömrü: tarayıcı oturumu.',
                    'XSRF-TOKEN: formları ve uygulamayı başka bir site tarafından sahte olarak oluşturulan isteklere karşı korur. Ömrü: tarayıcı oturumu.',
                    'Beni hatırla çerezi (adı "remember_" ile başlar): yalnızca oturumunuzun açık kalmasını seçerseniz yerleştirilir; böylece her ziyarette şifreniz sorulmaz.',
                    'zabuno_guest_locale: yayımlanmış bir menüde misafirin seçtiği dili hatırlar. Ömrü: bir yıl.',
                    'zabuno_measurement_consent: ölçüm çerezlerini kabul ettiğinizi ya da reddettiğinizi hatırlar; böylece her sayfada tekrar sorulmaz. Ömrü: bir yıl.',
                    'Açık veya koyu tema tercihiniz bir çerez değildir; tarayıcının yerel depolamasında zabuno-theme adıyla tutulur ve cihazınızdan çıkmaz.',
                ]),
                new LegalSection('Üçüncü tarafların yerleştirdiği ölçüm çerezleri', [
                    'Ölçüm araçları, yalnızca kabul etmenizden sonra Google Tag Manager üzerinden yüklenir. Karar verene kadar etiket yöneticisi veya ölçüm aracı yüklenmez ve üçüncü taraf çerezi yerleştirilmez.',
                    'Hangi araçların etkin olduğu hizmetin yapılandırmasına bağlıdır; bugün etkinleştirilebilen araçlar Google Analytics 4 ve Yandex Metrica\'dır. Çerezlerini bu sağlayıcılar yerleştirir ve bu çerezlere sağlayıcıların kendi politikaları uygulanır.',
                    'Ölçüm araçlarına hangi sayfanın görüntülendiği ve bir dönüşümün gerçekleşip gerçekleşmediği (örneğin fiyatlar sayfasının okunması veya iletişim formunun gönderilmesi) iletilir. Form içeriği, e-posta adresi veya ad hiçbir zaman iletilmez.',
                ]),
                new LegalSection('Tercihiniz', [
                    'Çerez tercih çubuğu, kabul veya ret kararınıza kadar sayfanın altında görünür. Tercihinizi bu sayfadaki tercih bölümünden istediğiniz zaman değiştirebilirsiniz; yeni tercih, açtığınız bir sonraki sayfadan itibaren geçerlidir.',
                    'Reddetmeniz hizmetin kullanılabilirliğini etkilemez. Üçüncü taraf bir aracın daha önce yerleştirdiği ölçüm çerezlerini tarayıcı ayarlarından silebilirsiniz.',
                ]),
                new LegalSection('Tarayıcı ayarları', [
                    'Her tarayıcı çerezleri görüntülemenizi, engellemenizi ve silmenizi sağlar. Oturum çerezini engellemek giriş yapmanızı önler; çünkü uygulama bu durumda isteklerinizi başkalarının isteklerinden ayıramaz.',
                ]),
            ],
        );
    }
}
