<?php

declare(strict_types=1);

namespace App\Domain\Legal;

use DateTimeImmutable;

/**
 * Bir onayın KAYDI: kim, hangi belgenin hangi sürümünü, ne zaman, nereden
 * (FF-198).
 *
 * `granted` alanı var çünkü bir onay kaydı yalnız "evet"leri tutmaz:
 * ticari ileti izninin GERİ ALINMASI da bir kayıttır ve aynı deftere
 * `granted = false` olarak yazılır. Sessizlik ise hiç yazılmaz — kayıt
 * anında işaretlenmeyen kutu için satır YOKTUR, çünkü sessizlik onay
 * değildir ve "hayır" da değildir.
 */
final class ConsentRecord
{
    public function __construct(
        public readonly ?int $userId,
        public readonly ?int $workspaceId,
        public readonly string $kind,
        public readonly string $documentKey,
        public readonly string $documentVersion,
        public readonly bool $granted,
        public readonly ?string $ip,
        public readonly ?string $userAgent,
        public readonly DateTimeImmutable $recordedAt,
    ) {}
}
