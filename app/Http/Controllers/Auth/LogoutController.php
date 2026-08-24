<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LogoutResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Overrides Fortify's default POST /logout so that, on top of invalidating
 * the session, cached guard resolutions (including the request-bound
 * Sanctum guard) are dropped too — otherwise a subsequent unauthenticated
 * request within the same PHP process could still see the stale resolved
 * user (S1WP02A-LOGOUT-01).
 */
final class LogoutController extends Controller
{
    public function __invoke(Request $request, StatefulGuard $guard): Response
    {
        $guard->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        Auth::forgetGuards();

        return app(LogoutResponse::class)->toResponse($request);
    }
}
