<?php

declare(strict_types=1);

namespace App\Http\Controllers\Team;

use App\Application\Entitlement\Exception\EntitlementDeniedException;
use App\Application\Entitlement\UseCase\RequireEntitlement;
use App\Application\Team\Exception\TeamInvitationConflictException;
use App\Application\Team\UseCase\CreateTeamInvitation;
use App\Application\Team\UseCase\DeliverTeamInvitation;
use App\Domain\Entitlement\Entitlement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreTeamInvitationRequest;
use Illuminate\Http\JsonResponse;

final class StoreTeamInvitationController extends Controller
{
    public function __construct(
        private readonly CreateTeamInvitation $createTeamInvitation,
        private readonly RequireEntitlement $requireEntitlement,
        private readonly DeliverTeamInvitation $deliverTeamInvitation,
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

        /*
            GÖNDERİM KAYITTAN SONRA GELİR VE SESSİZ KALAMAZ (`docs/110` P0-06).

            Burada çıplak bir `Mail::to(...)->send(...)` vardı. Taşıyıcı
            patlarsa istek 500 veriyor, davet ise OLUŞMUŞ oluyordu: sahip
            hata görüyor, tekrar deniyor ve "bu e-posta için zaten bekleyen
            bir davet var" cevabını alıyordu. Yani ekranda hiçbir iz
            bırakmadan, hem daveti hem sahibi kilitliyordu.

            Şimdi davet oluşmuşsa oluşmuş kalır (201), teslimatın olup
            olmadığı ise satıra yazılır ve yanıtla birlikte ekrana çıkar.
        */
        $delivery = $this->deliverTeamInvitation->handle($invitation);

        return response()->json($invitation->summary($delivery)->toArray(), 201);
    }
}
