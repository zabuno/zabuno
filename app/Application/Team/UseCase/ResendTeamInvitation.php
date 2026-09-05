<?php

declare(strict_types=1);

namespace App\Application\Team\UseCase;

use App\Application\Team\Dto\TeamInvitationSummary;
use App\Application\Team\Port\TeamInvitationRepositoryPort;

/**
 * Bekleyen bir daveti yeniden gönderir (`docs/110` P0-06, kabul ölçütü 3).
 *
 * Öncesinde sahibin tek hamlesi daveti İPTAL edip yeniden kurmaktı: yani
 * ekibini kurabilmek için önce onu bozması gerekiyordu. Yeniden gönderme
 * daveti bozmaz — aynı satırı tazeler ve postayı tekrar dener.
 *
 * Bekleyen olmayan bir davet yeniden gönderilemez ve bu bir ayrıntı değil:
 * iptal edilmiş bir daveti yeniden göndermek, sahibin kapattığı kapıyı
 * sessizce açmak olurdu.
 */
final class ResendTeamInvitation
{
    public function __construct(
        private readonly TeamInvitationRepositoryPort $invitations,
        private readonly DeliverTeamInvitation $deliver,
    ) {}

    public function handle(int $workspaceId, int $invitationId): ?TeamInvitationSummary
    {
        $invitation = $this->invitations->refreshPendingForResend($workspaceId, $invitationId);

        if ($invitation === null) {
            return null;
        }

        return $invitation->summary($this->deliver->handle($invitation));
    }
}
