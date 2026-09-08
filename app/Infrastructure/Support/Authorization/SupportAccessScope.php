<?php

declare(strict_types=1);

namespace App\Infrastructure\Support\Authorization;

use App\Application\Support\Port\SupportAccessPort;

/**
 * "Bu kullanıcı şu an hangi kiracıya bakıyor?" — tek cümlelik cevap.
 *
 * Ayrı bir sınıf, çünkü aynı soruyu iki sarmalayıcı (yetki ve depo) soruyor
 * ve ikisinin cevabı bir gün ayrışırsa, ekranın çizdiği ile sunucunun izin
 * verdiği ayrışır. Tek kaynak, o ayrışmayı imkânsız kılar.
 */
final class SupportAccessScope
{
    public function __construct(
        private readonly SupportAccessPort $supportAccess,
    ) {}

    public function workspaceIdFor(int $userId): ?int
    {
        return $this->supportAccess->activeFor($userId)?->workspaceId;
    }
}
