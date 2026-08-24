<?php

declare(strict_types=1);

namespace App\Http\Controllers\Team;

use App\Application\Team\UseCase\AcceptTeamInvitation;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AcceptTeamInvitationController extends Controller
{
    public function __construct(
        private readonly AcceptTeamInvitation $acceptTeamInvitation,
    ) {}

    public function __invoke(Request $request, string $token): JsonResponse|Response
    {
        $user = $request->user();
        $userId = (int) $user->getKey();

        if (! $this->acceptTeamInvitation->handle($token, (string) $user->email, $userId)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->noContent();
    }
}
