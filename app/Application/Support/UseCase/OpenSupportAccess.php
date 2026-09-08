<?php

declare(strict_types=1);

namespace App\Application\Support\UseCase;

use App\Application\Support\Dto\SupportAccessSessionRow;
use App\Application\Support\Port\SupportAccessNotifierPort;
use App\Application\Support\Port\SupportAccessPort;
use App\Application\Tenancy\Port\WorkspaceContextSessionPort;
use App\Domain\Support\SupportAccessWindow;

/**
 * Kiracı olarak bakma oturumunu açar — `docs/122` Y7, `docs/133`.
 *
 * SIRA KASITLIDIR ve `docs/93`'ün sırasıyla aynıdır: önce KAYIT, sonra
 * haber verme. Bundan sonrası düşse bile kayıt durur; kiracının panelinde
 * "biri baktı" satırı, e-posta çıkmasa da görünür. Ters sıra kurulsaydı,
 * postası düşen bir kurulumda bakış sessiz kalırdı.
 *
 * BAĞLAM SUNUCUDA KURULUR. Destek görevlisi kiracıyı bir yazma isteğiyle
 * (`PUT /api/workspace-context`) seçmez — o yol zaten salt-okunur kilidin
 * arkasında kapalıdır. Bağlamı oturumun kendisi belirler ve destek görevlisi
 * o oturum boyunca başka bir restorana geçemez: bakış tek kiracıya
 * çivilenir.
 */
final class OpenSupportAccess
{
    public function __construct(
        private readonly SupportAccessPort $sessions,
        private readonly SupportAccessNotifierPort $notifier,
        private readonly WorkspaceContextSessionPort $context,
        private readonly SupportAccessWindow $window,
    ) {}

    public function handle(int $workspaceId, int $actorUserId, string $reason, string $workspaceName): SupportAccessSessionRow
    {
        $session = $this->sessions->open($workspaceId, $actorUserId, $reason, $this->window->minutes());

        $this->context->setCurrentWorkspaceId($workspaceId);

        $this->sessions->recordOwnerNotification(
            $session->id,
            $this->notifier->notifyWorkspaceOwners($session, $workspaceName),
        );

        return $session;
    }
}
