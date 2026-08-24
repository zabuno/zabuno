<?php

declare(strict_types=1);

namespace App\Http\Controllers\Team;

use App\Application\Team\Port\TeamInvitationRepositoryPort;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowTeamInvitationController extends Controller
{
    public function __construct(
        private readonly TeamInvitationRepositoryPort $invitations,
    ) {}

    public function __invoke(Request $request, string $token): JsonResponse|View
    {
        $user = $request->user();
        $authenticatedUserId = $user?->getKey() !== null ? (int) $user->getKey() : null;
        $authenticatedUserEmail = $user?->hasVerifiedEmail() ? $user->email : null;

        $details = $this->invitations->findDetailsForRawToken(
            $token,
            $authenticatedUserId,
            $authenticatedUserEmail,
        );

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json(['data' => $details->toArray()]);
        }

        $loginUrl = '/login?redirect='.urlencode("/invitations/{$token}");

        if ($details->state !== 'available') {
            return view('auth.invitation', [
                'state' => $details->state,
                'authenticated' => $details->authenticated,
                'loginUrl' => $loginUrl,
                'workspaceName' => null,
                'invitedEmail' => null,
                'role' => null,
                'acceptUrl' => null,
            ]);
        }

        return view('auth.invitation', [
            'state' => $details->state,
            'authenticated' => $details->authenticated,
            'loginUrl' => $loginUrl,
            'workspaceName' => $details->workspaceName,
            'invitedEmail' => $details->email,
            'role' => $details->role,
            'acceptUrl' => $details->acceptUrl,
        ]);
    }
}
