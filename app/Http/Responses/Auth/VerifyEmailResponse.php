<?php

declare(strict_types=1);

namespace App\Http\Responses\Auth;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * Always returns a 2xx status (never a redirect) so the signed
 * verification link is a safe, idempotent HTTP endpoint for JSON callers
 * (S1WP02A-VERIFY-01/03). The verification route content-negotiates: a
 * browser (non-JSON) request is redirected to /app before this class is
 * ever invoked, so both branches below are reachable only via wantsJson().
 */
final class VerifyEmailResponse implements VerifyEmailResponseContract
{
    public function toResponse($request): Response
    {
        /** @var Request $request */
        if ($request->wantsJson()) {
            return response()->json(['status' => 'verified']);
        }

        return response()->view('auth.verified', [], 200);
    }
}
