<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
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
    public function __invoke(Request $request, CreatesNewUsers $creator, StatefulGuard $guard): Response
    {
        event(new Registered($user = $creator->create($request->all())));

        $guard->login($user, $request->boolean('remember'));

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return app(RegisterResponse::class)->toResponse($request);
    }
}
