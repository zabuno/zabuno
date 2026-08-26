<?php

declare(strict_types=1);

namespace App\Http\Controllers\Team;

use App\Application\Entitlement\Exception\EntitlementDeniedException;
use App\Application\Entitlement\UseCase\RequireEntitlement;
use App\Application\Team\Exception\TeamInvitationConflictException;
use App\Application\Team\UseCase\CreateTeamInvitation;
use App\Domain\Entitlement\Entitlement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreTeamInvitationRequest;
use App\Mail\TeamInvitationMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

final class StoreTeamInvitationController extends Controller
{
    public function __construct(
        private readonly CreateTeamInvitation $createTeamInvitation,
        private readonly RequireEntitlement $requireEntitlement,
    ) {}

    public function __invoke(StoreTeamInvitationRequest $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        // CORE-04: bu yetenek plana bağlıdır (owner kararı, 2026-08-26).
        // 402 kullanılır, 403 değil: kullanıcı yetkisiz DEĞİL, planı bu
        // yeteneği içermiyor. Çıkış yolu farklıdır — biri erişim talebi,
        // diğeri plan yükseltmesidir.
        try {
            $this->requireEntitlement->handle($workspace, Entitlement::TeamInvitations);
        } catch (EntitlementDeniedException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'entitlement' => $e->entitlement->value,
            ], 402);
        }

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
