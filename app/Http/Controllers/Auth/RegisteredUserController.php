<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Legal\ConsentRecorder;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Contracts\RegisterResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Overrides Fortify's default POST /register route (which is guarded by
 * `guest:web`) so a stale/current unverified session never blocks a new
 * registration attempt (docs/33, S1WP02A-AI-01 repeats registration on the
 * same client without logging out first).
 */
final class RegisteredUserController extends Controller
{
    public function __invoke(
        Request $request,
        CreatesNewUsers $creator,
        StatefulGuard $guard,
        ConsentRecorder $consents,
    ): Response {
        /*
            HESAP VE ONAY KAYDI AYNI İŞLEMDE (FF-198).

            Onay, hesabın parçasıdır: hesap yazılıp onay yazılamazsa elimizde
            neyi kabul ettiğini bilmediğimiz bir hesap kalırdı. İkisi tek
            işlemde; biri düşerse ikisi de geri alınır. Doğrulama hatası
            (`CreateNewUser`) da işlemin içinde düşer ve hiçbir şey yazılmaz.

            `Registered` olayı (doğrulama e-postası) işlemden SONRA: kaydı
            olmayan bir hesaba e-posta göndermek anlamsız olurdu.
        */
        $user = DB::transaction(function () use ($request, $creator, $consents) {
            $user = $creator->create($request->all());

            $consents->recordRegistration(
                (int) $user->getAuthIdentifier(),
                $request,
                $request->boolean('marketing_consent'),
                $request->boolean('privacy_acknowledged'),
            );

            return $user;
        });

        event(new Registered($user));

        $guard->login($user, $request->boolean('remember'));

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return app(RegisterResponse::class)->toResponse($request);
    }
}
