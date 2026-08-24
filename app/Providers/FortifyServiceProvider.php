<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\Auth\LoginResponse;
use App\Http\Responses\Auth\PasswordResetLinkResponse;
use App\Http\Responses\Auth\VerifyEmailResponse;
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
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::verifyEmailView(fn () => view('auth.verify'));
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
