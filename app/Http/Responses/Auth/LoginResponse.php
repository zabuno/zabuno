<?php

declare(strict_types=1);

namespace App\Http\Responses\Auth;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

final class LoginResponse implements LoginResponseContract
{
    /**
     * Server-side safe-redirect handoff (S1-WP01A delivery journey): the
     * only intended destination ever honored here is the exact local
     * /invitations/{64-char opaque token} path stashed in the session by
     * GET /login?redirect=... (see FortifyServiceProvider::boot()).
     * Anything else — including a re-validation failure, in case the
     * session value was tampered with — falls back to /app.
     */
    public function toResponse($request)
    {
        $intended = session()->pull('url.intended');
        $redirect = is_string($intended) && self::isSafeInvitationPath($intended) ? $intended : '/app';

        if ($request instanceof Request && $request->wantsJson()) {
            return response()->json(['redirect' => $redirect]);
        }

        return redirect()->to($redirect);
    }

    public static function isSafeInvitationPath(string $path): bool
    {
        return (bool) preg_match('#^/invitations/[A-Za-z0-9_-]{64}$#', $path);
    }
}
