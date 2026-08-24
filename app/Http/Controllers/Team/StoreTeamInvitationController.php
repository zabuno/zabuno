<?php

declare(strict_types=1);

namespace App\Http\Controllers\Team;

use App\Application\Team\Exception\TeamInvitationConflictException;
use App\Application\Team\UseCase\CreateTeamInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreTeamInvitationRequest;
use App\Mail\TeamInvitationMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

final class StoreTeamInvitationController extends Controller
{
    public function __construct(
        private readonly CreateTeamInvitation $createTeamInvitation,
    ) {}

    public function __invoke(StoreTeamInvitationRequest $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        try {
            $invitation = $this->createTeamInvitation->handle(
                $workspace,
                (string) $request->validated('email'),
                (string) $request->validated('role'),
                $userId,
            );
        } catch (TeamInvitationConflictException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        Mail::to($invitation->email)->send(new TeamInvitationMail(
            $invitation->workspaceName,
            $invitation->role,
            url("/invitations/{$invitation->rawToken}"),
            $invitation->expiresAt,
        ));

        return response()->json($invitation->summary()->toArray(), 201);
    }
}
