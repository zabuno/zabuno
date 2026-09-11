<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Middleware\EnsurePasswordResetTransportAvailable;
use App\Http\Responses\Auth\LoginResponse;
use App\Http\Responses\Auth\PasswordResetLinkResponse;
use App\Http\Responses\Auth\VerifyEmailResponse;
use App\Support\Legal\RegistrationLegalPayload;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse as FailedPasswordResetLinkRequestResponseContract;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse as SuccessfulPasswordResetLinkRequestResponseContract;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Laravel\Fortify\Fortify;

final class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VerifyEmailResponseContract::class, VerifyEmailResponse::class);
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(SuccessfulPasswordResetLinkRequestResponseContract::class, PasswordResetLinkResponse::class);
        $this->app->singleton(FailedPasswordResetLinkRequestResponseContract::class, PasswordResetLinkResponse::class);
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(function (Request $request) {
            $redirect = $request->query('redirect');

            if (is_string($redirect) && LoginResponse::isSafeInvitationPath($redirect)) {
                $request->session()->put('url.intended', $redirect);
            }

            return view('auth.login');
        });
        /*
            KAYIT EKRANI, KABUL EDİLEN METNİ YANINDA TAŞIR (REG-LEGAL-01).

            Metin sayfayla birlikte gelir; ayrı bir uç nokta yok
            (`RegistrationLegalPayload`). Böylece kaydolan kişi formu terk
            etmeden — yani yazdıklarını kaybetmeden — imzaladığı metni
            okuyabilir.
        */
        Fortify::registerView(fn () => view('auth.register', [
            'legal' => app(RegistrationLegalPayload::class)->forLocale(app()->getLocale()),
        ]));
        /*
            EKRAN, POSTANIN HANGİ ADRESE GİTTİĞİNİ SÖYLEMELİ.

            Görünüm buraya kadar veri ALMADAN çağrılıyordu ve
            `auth.verify` şablonu `$email ?? ''` diye soruyordu; yani
            değişken hiçbir zaman dolmuyor, ekranda "… adresine bir
            doğrulama bağlantısı gönderdik" cümlesi ADRESSİZ çıkıyordu.

            Postası gelmeyen kullanıcının ilk sorusu tam olarak budur:
            "adresi yanlış mı yazdım?" Adres ekranda yoksa o soruyu
            cevaplayamaz ve yapabileceği tek şey aynı düğmeye tekrar
            basmaktır. Kullanıcı 2026-09-11'de bunu bildirdi.
        */
        Fortify::verifyEmailView(fn (Request $request) => view('auth.verify', [
            'email' => (string) ($request->user()?->email ?? ''),
        ]));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', [
            'token' => (string) $request->route('token'),
            'email' => (string) $request->query('email', ''),
        ]));

        /**
         * Fortify's own route registration attaches no throttle middleware
         * to POST /forgot-password (password.email). Its routes load lazily
         * (deferred past this provider's own boot-time callbacks), so the
         * named 6/minute IP limiter is enforced via a route-matched
         * listener instead of mutating route middleware directly (PRD-03).
         */
        Route::matched(function (RouteMatched $event): void {
            if (! $event->route->named('password.email')) {
                return;
            }

            $executed = RateLimiter::attempt('password-reset|'.$event->request->ip(), 6, fn () => true, 60);

            if (! $executed) {
                abort(429);
            }

            /*
                ÖN KONTROL ROTA YIĞININA GİRER, BURADA KOŞMAZ.

                `RouteMatched` rota ara katmanlarından önce doğar: oturum
                (`StartSession`) henüz kurulmamıştır, dolayısıyla burada
                üretilecek bir HTML hata yönlendirmesinin hata kesesi
                kaydedilemez ve kullanıcı sebepsiz boş bir forma döner.
                Ama bu olay yığın ÇALIŞMADAN önce doğduğu için rotanın
                ara katman listesine hâlâ ekleme yapılabilir: kontrol
                oturumun arkasında, hesap aranmadan önce koşar.

                Liste bir kez genişletilir; rota nesnesi istekler arasında
                yaşadığı için tekrar eklemek aynı kontrolü boşuna
                çoğaltırdı. Kontrol rotanın KENDİ listesine bakar,
                `gatherMiddleware()`'e DEĞİL: o çağrı sonucu önbelleğe alır
                ve bu satırdan sonra eklenen her ara katmanı görünmez
                kılardı — kontrol sessizce hiç koşmazdı.
            */
            if (! in_array(EnsurePasswordResetTransportAvailable::class, $event->route->middleware(), true)) {
                $event->route->middleware(EnsurePasswordResetTransportAvailable::class);
            }
        });

        RateLimiter::for('login', function (Request $request): Limit {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('verification', function (Request $request): Limit {
            $throttleKey = ($request->user()?->getKey() ?? $request->ip()).'|verification';

            return Limit::perMinute(6)->by((string) $throttleKey);
        });

        RateLimiter::for('register', function (Request $request): array {
            $normalizedEmail = Str::transliterate(Str::lower(trim((string) $request->input('email'))));
            $throttleKey = $normalizedEmail.'|'.$request->ip();

            return [
                Limit::perMinute(5)->by($throttleKey),
                Limit::perMinute(60)->by('register-ip|'.$request->ip()),
            ];
        });
    }
}
