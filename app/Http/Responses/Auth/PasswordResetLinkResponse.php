<?php

declare(strict_types=1);

namespace App\Http\Responses\Auth;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse as FailedPasswordResetLinkRequestResponseContract;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse as SuccessfulPasswordResetLinkRequestResponseContract;

/**
 * Account-enumeration-safe forgot-password response (PRD-02): bound to
 * both the success and failure Fortify contracts so a known and an
 * unknown email always produce the byte-identical generic response,
 * regardless of the underlying broker status.
 */
final class PasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponseContract, SuccessfulPasswordResetLinkRequestResponseContract
{
    public function __construct(private readonly string $status = '') {}

    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['message' => trans('passwords.sent')], 200);
        }

        return back()->with('status', trans('passwords.sent'));
    }
}
