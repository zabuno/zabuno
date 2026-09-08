<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Application\Assurance\Port\AssuranceLibraryPort;
use App\Http\Controllers\Controller;
use App\Support\Localization\PageLanguage;
use App\Support\Site\SiteShell;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * GÜVEN MERKEZİ — "satın almadan önce neyi bilmem gerekiyor?" (FF-252,
 * `docs/107` Faz 3).
 *
 * ═══ OTURUM İSTEMEZ, VERİTABANINA DOKUNMAZ ═══
 *
 * Bu sayfayı okuyan kişinin henüz bir hesabı yoktur; hesabı olsaydı zaten
 * satın almıştı. Ölçülen her şey yapılandırmadan ve kasanın DURUM
 * portundan gelir — `PlatformCredentialAdminPort` bir sırrı geri okuyamaz,
 * yalnız "var mı/etkin mi" söyler. Herkese açık bir sayfayı çizen kod, bir
 * anahtarın değerine fiziksel olarak erişemez.
 *
 * ═══ NEDEN BİR YASAL BELGE DEĞİL ═══
 *
 * `/trust` bir sözleşme değildir ve `LegalLibraryPort::KEYS` listesine
 * girmez. Sözleşme metinlerinin sürümü ve yürürlük tarihi vardır çünkü onay
 * kaydı o sürüme yazılır; bu sayfanın içeriği ise her çizimde o anki
 * yapılandırmadan doğar ve bir sürüm numarası ona yanlış bir kalıcılık
 * verirdi. Bağlantısı da bu yüzden altbilginin yasal satırında değil,
 * şirket grubunda (`SiteNavigation`).
 *
 * ═══ EKSİK ŞİRKET KİMLİĞİNDE `noindex` YOK — VE BU BİLEREK ═══
 *
 * `/about` ve `/sla` şirket kimliği girilmemişken `noindex` döner, çünkü
 * ikisi de satıcıyı ADIYLA anmak zorunda olan belgelerdir: tarafı "not yet
 * provided" yazan bir sözleşme gönderilebilir bir belge değildir. Bu sayfa
 * bir taraf beyan etmez; neyin ölçüldüğünü ve neyin ölçülmediğini söyler ve
 * o cümleler satıcının ünvanı girilmeden de doğrudur. `/acceptable-use` ve
 * `/third-party-licenses` aynı sınırın öteki tarafında duruyor.
 */
final class ShowTrustCentreController extends Controller
{
    public function __construct(
        private readonly SiteShell $shell,
        private readonly AssuranceLibraryPort $assurance,
    ) {}

    public function __invoke(Request $request): View
    {
        $context = $this->shell->context($request, 'trust', '/trust');
        $statement = $this->assurance->trustCentre($context['lang']->ui);
        $context['lang'] = PageLanguage::for($statement->language);

        return view('public.assurance', $context + [
            'statement' => $statement,
            /*
                ÖNSÖZ YÜZÜ — `orbit` DEĞİL, `grid`.

                Yüz sayfanın anlamından türer (`docs/146` §10.1): güven bir
                ZEMİN sorusudur — neyin üstünde durduğunuzu bilmek. Yörünge
                bir sistemi, veri hattı bir işlemi anlatır; ikisi de burada
                yanlış cümle olurdu. `/pricing` de aynı sebeple `grid`
                taşıyor ve bu bir çakışma değil: iki sayfa da alıcının aynı
                sorusunu soruyor.
            */
            'prologueVariant' => 'grid',
        ]);
    }
}
