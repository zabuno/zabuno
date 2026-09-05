<?php

declare(strict_types=1);

namespace App\Application\Team\Dto;

use App\Domain\Team\InvitationDeliveryState;
use DateTimeInterface;

final class IssuedTeamInvitation
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $role,
        public readonly string $status,
        public readonly string $rawToken,
        public readonly DateTimeInterface $expiresAt,
        public readonly string $workspaceName,
    ) {}

    /**
     * Teslimat hâli DIŞARIDAN verilir.
     *
     * Davet kaydı, e-posta denenmeden önce doğar; o anda teslimatın hâli
     * henüz yoktur. Buraya bir varsayılan koymak, gönderim hiç denenmemişken
     * satıra bir cevap yazmak olurdu.
     */
    public function summary(InvitationDeliveryState $delivery): TeamInvitationSummary
    {
        return new TeamInvitationSummary($this->id, $this->email, $this->role, $this->status, $delivery);
    }
}
