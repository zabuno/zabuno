<?php

declare(strict_types=1);

namespace App\Application\Media\Port;

use App\Application\Media\Dto\MediaQuotaStatus;

/** Kota (`docs/49` Faz 7 madde 1-2, rakamlar `config/media-quota.php`). */
interface MediaQuotaPort
{
    public function statusFor(int $workspaceId): MediaQuotaStatus;

    /**
     * Bu boyutta yeni bir yükleme kabul edilir mi? Reddediliyorsa SAHİBİN
     * okuyacağı sebep döner; kabulse null.
     */
    public function admits(int $workspaceId, int $incomingBytes): ?string;

    public function trashRetentionDaysFor(int $workspaceId): int;
}
