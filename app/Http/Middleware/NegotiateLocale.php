<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Localization\LanguageNegotiator;
use App\Support\Localization\LanguageType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * İsteğin ARAYÜZ dilini SEÇER — `docs/26` CORE-08'in eksik halkası (FF-93).
 *
 * Ürün altı dil taşıyor ve katalogların hepsi yerinde; ama hiçbir yerde bir
 * SEÇİM yapılmıyordu. `app()->getLocale()` her istekte yapılandırmadaki `en`
 * kalıyor, `<html lang>` de ondan türüyordu. İstemci çevirici locale'i
 * `<html lang>`'den okuduğu için, yazılmış Türkçe çevirileri hiçbir Türk
 * kullanıcı GÖREMİYORDU — çeviri vardı, kapı yoktu.
 *
 * Seçim SUNUCUDA yapılır, JavaScript'te değil: dil, ilk boyanan pikselden
 * önce belli olmalıdır. Sonradan değiştirilirse kullanıcı önce yanlış dilde
 * bir sayfa görür, sonra sayfanın altından dili değişir.
 *
 * ═══ 2026-09-05: KARAR ARTIK BİR ZİNCİRDEN GELİYOR ═══
 *
 * Burada tek bir sinyal okunuyordu (`Accept-Language`) ve kural koda
 * gömülüydü. `docs/120` §4 bunu bir AĞIRLIKLI ÇÖZÜCÜ ZİNCİRİNE çevirdi:
 * açık seçim → oturum parametresi → tarayıcı → bölge → kaynak dil. Sıra
 * yapılandırmadadır; bir sıralama denemesi artık bir dağıtım değil, bir
 * ayardır.
 *
 * Bu ara katman YALNIZ ARAYÜZ dilini kurar. İçerik dili adresten gelir ve
 * pazarlığa girmez (`CORP-LOCALE-FROM-PATH-01`): `/tr/urun/qr-menu/` Türkçe
 * YAZILMIŞ bir sayfadır ve bir tarayıcı ayarı onu İngilizceye çeviremez.
 *
 * Sunulan dil süzgeci zincirin içinde yaşıyor (`i18n.negotiation.shipped_only`)
 * ve davranış değişmedi: sunulmayan bir dil hâlâ arayüze giremez, çünkü yarım
 * çeviri çevirisizlikten kötüdür.
 */
final class NegotiateLocale
{
    public function __construct(private readonly LanguageNegotiator $negotiator) {}

    public function handle(Request $request, Closure $next): Response
    {
        $language = $this->negotiator->negotiate(LanguageType::Interface, $request);

        /*
            Zincir bir cevap üretemediyse hiçbir şey değiştirilmez.

            Bugünkü yapılandırmada kaynak dil çözücüsü zincirin sonunda
            durduğu için bu dal pratikte çalışmaz; ama zincir boşaltılabilir
            bir ayardır ve boş bir zincirin uygulamayı dilsiz bırakması kabul
            edilemez.

            Kaynak dil çözücüsünün O ANDA ÇALIŞAN dili döndürmesi de bu
            yüzden: sinyalsiz bir istekte dili başka bir yerde (bir konsol
            komutu, bir testin kurduğu bağlam) bilerek ayarlamış olan taraf
            sessizce ezilmez.
        */
        if ($language !== null) {
            app()->setLocale($language);
        }

        return $next($request);
    }
}
