<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Mail\Port\MailTransportSelectorPort;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * TAŞIYICI ARIZALIYSA, HESAP ARANMADAN ÖNCE DUR (password.email).
 *
 * Kimlik girilmiş ama yapılandırma taşınamıyorsa gönderici seçimi istisna
 * atar (bkz. `MailTransportSelectorPort`). Bu kontrol yapılmazsa istisna,
 * hesap arandıktan SONRA — bildirim hazırlanırken — doğar ve iki farklı
 * cevap üretir: kayıtlı adres 500, kayıtsız adres 200. O fark tek başına
 * bir hesap sayma aracıdır; "bu e-posta bizde var mı?" sorusu, cevabı hiç
 * yazmadığımız hâlde cevaplanmış olur.
 *
 * Bu yüzden ön kontrol e-posta OKUNMADAN, doğrulamadan ve broker
 * çağrısından önce yapılır: girilen adres ne olursa olsun cevap aynıdır.
 * Cevap "gönderdik" DEĞİLDİR — gönderemedik; sahibin bu paketi doğuran
 * şikâyeti tam olarak "gönderdik" yazıp hiçbir şeyin gelmemesiydi.
 *
 * NEDEN ARA KATMAN, NEDEN `RouteMatched` DEĞİL. Aynı kontrol daha önce
 * throttle ile birlikte `Route::matched` dinleyicisindeydi. O olay rota
 * yığınından ÖNCE, yani `StartSession`'dan da önce koşar: orada üretilen
 * HTML cevabı `back()->withErrors(...)` ile hata kesesini yazar ama o
 * keseyi kaydedecek oturum katmanı henüz kurulmamıştır — kullanıcı
 * yönlendirmenin ardından BOŞ bir forma döner ve ekranda hiçbir sebep
 * görmez. Ara katman rota yığınının içindedir: oturum açıktır, hata
 * kesesi yazıldığı yerde kalıcıdır, cevap dönüş yolunda düzgün kaydedilir.
 *
 * Sağlam yapılandırmada bu katmanın hiçbir etkisi yoktur: seçim zaten
 * gönderim anında da yapılacaktı ve çözülen gönderici önbelleğe alınır.
 */
final class EnsurePasswordResetTransportAvailable
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Yalnız seçim değil, seçilenin KURULABİLİRLİĞİ de burada
            // sınanır: yapılandırmada karşılığı olmayan bir gönderici adı
            // da aynı çatallanmayı üretirdi. Ağ yok — taşıyıcı kurulur,
            // kullanılmaz.
            Mail::mailer(app(MailTransportSelectorPort::class)->select());

            return $next($request);
        } catch (Throwable $exception) {
            /*
                SEBEP DEĞİL, SINIF. Bu yol sağlayıcı katmanına kadar
                inebilir ve oradan gelen bir mesaj DSN, uç adres ya da
                anahtar taşıyabilir. Operatörün burada ihtiyacı olan tek
                şey arızanın hangi noktada doğduğudur; ayrıntı, mesajı
                kendisi arındıran seçicinin kendi istisnasında durur.
            */
            Log::warning('Şifre sıfırlama isteği karşılanamadı: posta taşıyıcısı yapılandırması arızalı.', [
                'route' => 'password.email',
                'reason' => $exception::class,
            ]);
        }

        /*
            Cevap her iki istemci biçimi için de aynı arındırılmış metindir:
            ne girilen adres, ne kayıtlı olup olmadığı, ne de arıza ayrıntısı
            geri döner.
        */
        if ($request->wantsJson()) {
            return new JsonResponse(['message' => trans('auth.password_reset_unavailable')], 503);
        }

        return back()->withErrors(['email' => trans('auth.password_reset_unavailable')]);
    }
}
