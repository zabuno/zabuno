<?php

declare(strict_types=1);

namespace App\Application\Platform\Port;

use App\Application\Billing\UseCase\ManageCheckout;

/**
 * Platform denetim izi — append-only, SIRSIZ.
 *
 * Kasa denetiminin (`platform_credential_audits`) sağlayıcıya bağlı olmayan
 * kardeşi: kip anahtarı, iade gibi platform düzeyi kararlar. `details`
 * okunabilir olgudur (tutar, sebep, önce/sonra) — anahtar, jeton, kart
 * asla girmez.
 *
 * @see ManageCheckout
 */
interface PlatformAuditPort
{
    /** @param array<string, mixed> $details */
    public function record(string $scope, string $action, ?string $subject, array $details = [], ?int $actorUserId = null): void;
}
