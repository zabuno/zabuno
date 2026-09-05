<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * İsteğin dilini SEÇER — `docs/26` CORE-08'in eksik kalan halkası (FF-93).
 *
 * Ürün altı dil taşıyor ve katalogların hepsi yerinde; ama hiçbir yerde bir
 * SEÇİM yapılmıyordu. `app()->getLocale()` her istekte yapılandırmadaki `en`
 * kalıyor, `<html lang>` de ondan türüyordu. İstemci çevirici locale'i
 * `<html lang>`'den okuduğu için, yazılmış Türkçe çevirileri hiçbir Türk
 * kullanıcı GÖREMİYORDU — çeviri vardı, kapı yoktu.
 *
 * Kamu sayfaları bunu tek tek kendi içinde çözüyordu; kabuklar hiç
 * çözmüyordu. Aynı üründe iki farklı gerçek vardı ve hangisinin geçerli
 * olduğu sayfaya göre değişiyordu.
 *
 * Seçim SUNUCUDA yapılır, JavaScript'te değil: dil, ilk boyanan pikselden
 * önce belli olmalıdır. Sonradan değiştirilirse kullanıcı önce yanlış dilde
 * bir sayfa görür, sonra sayfanın altından dili değişir.
 *
 * Bölgeli etiket (`tr-TR`) taban dile (`tr`) iner: katalog taban dillerle
 * anahtarlanır ve `tr-TR` yüzünden birini İngilizceye düşürmek, desteklenen
 * bir dili desteklenmiyormuş gibi göstermek olurdu.
 */
final class NegotiateLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        /*
            Başlık YOKSA hiçbir şey seçilmez ve yapılandırılmış dil olduğu
            gibi kalır. Sinyal yokken karar vermek, dili başka bir yerde
            (bir konsol komutu, bir testin kurduğu bağlam, ileride bir
            kullanıcı tercihi) bilerek ayarlamış olan tarafı sessizce
            ezmek olurdu.
        */
        if ($request->headers->has('Accept-Language')) {
            $preferred = $request->getPreferredLanguage(self::supported());

            if (is_string($preferred) && $preferred !== '') {
                app()->setLocale(self::baseLanguage($preferred));
            }
        }

        return $next($request);
    }

    /**
     * Desteklenen diller. İlk sıradaki, başlık hiç gelmediğinde ya da
     * hiçbiri eşleşmediğinde seçilen dildir — o yüzden kaynak dil başta.
     *
     * @return array<int, string>
     */
    private static function supported(): array
    {
        /*
            SUNULAN DİL LİSTESİ — `app.supported_locales` DEĞİL.

            Önce `app.supported_locales` okunuyordu: altı dillik bir liste.
            Ama hangi dilin gerçekten SUNULABİLECEĞİNE karar veren yer
            `i18n.shipped_locales` ve o listedeki her dilin katalogu TAM olmak
            zorunda (`ShippedLocalesAreCompleteTest`).

            İki liste ayrışınca sonucu sahibin ekranında görüldü (2026-09-05):
            Türkçe tarayıcı Türkçe belge alıyor, katalog tam olmadığı için
            ekranda KARIŞIK DİL beliriyordu — "Menus" ve "Preview & publish"
            İngilizce, "Ürün ekle" ve "Hepsi tükendi" Türkçe. Yarım çeviri
            çevirisizlikten kötüdür: kullanıcı ürünün bozuk olduğunu düşünür.

            `app.supported_locales` SİLİNMEDİ ve bu kasıtlı: kurumsal site
            (`/tr/…`) kendi dil uzayını o listeden türetiyor ve orada çeviri
            gerçekten tam. İki liste iki ayrı soruya cevap veriyor — "bu dilde
            bir pazarlama sayfamız var mı" ile "bu dilde bir ÜRÜN sunabiliyor
            muyuz". Tek listeye indirmek, ikisinden birini yalan söylemeye
            zorlardı.
        */
        /** @var array<int, string> $configured */
        $configured = config('i18n.shipped_locales', []);

        $fallback = (string) config('app.locale', 'en');

        if ($configured === []) {
            return [$fallback];
        }

        return array_values(array_unique([$fallback, ...$configured]));
    }

    private static function baseLanguage(string $tag): string
    {
        return strtolower(explode('-', str_replace('_', '-', $tag))[0]);
    }
}
