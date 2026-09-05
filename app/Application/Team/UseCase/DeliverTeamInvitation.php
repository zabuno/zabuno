<?php

declare(strict_types=1);

namespace App\Application\Team\UseCase;

use App\Application\Team\Dto\IssuedTeamInvitation;
use App\Application\Team\Port\TeamInvitationNotifierPort;
use App\Application\Team\Port\TeamInvitationRepositoryPort;
use App\Domain\Team\InvitationDeliveryState;

/**
 * Daveti gönderir — KAYITTAN SONRA (`docs/93` deseni, `docs/110` P0-06).
 *
 * `StoreContactMessageController` bu sırayı bir kez öğrendi: saklamak
 * göndermekten önce gelir ve gönderim başarısız olsa bile kayıt durur.
 * Davet için de aynıdır ve sebebi daha ağırdır — davet zaten oluşmuştur,
 * geri alınsaydı sahip tekrar denediğinde ÇİFT davet üretilirdi ve alıcının
 * elindeki bağlantı geçersizleşirdi.
 *
 * Burası "gönderildi" DEMEZ; ne olduğunu döner. Çağıran ekrana o hâli yazar,
 * bir söz değil.
 */
final class DeliverTeamInvitation
{
    public function __construct(
        private readonly TeamInvitationNotifierPort $notifier,
        private readonly TeamInvitationRepositoryPort $invitations,
    ) {}

    public function handle(IssuedTeamInvitation $invitation): InvitationDeliveryState
    {
        $failure = $this->notifier->notify($invitation);

        $this->invitations->recordDeliveryOutcome($invitation->id, $failure);

        return $failure === null
            ? InvitationDeliveryState::Sent
            : InvitationDeliveryState::Failed;
    }
}
