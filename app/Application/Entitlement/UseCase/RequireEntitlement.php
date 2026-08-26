<?php

declare(strict_types=1);

namespace App\Application\Entitlement\UseCase;

use App\Application\Entitlement\Exception\EntitlementDeniedException;
use App\Application\Entitlement\Port\EntitlementRepositoryPort;
use App\Domain\Entitlement\Entitlement;

/**
 * Bir yeteneğin planda bulunduğunu doğrular; bulunmuyorsa fırlatır.
 *
 * Sunucu tarafında zorunludur. Arayüz bir düğmeyi gizleyebilir ama bu bir
 * güvenlik sınırı DEĞİLDİR — istek yine de elle gönderilebilir. Yetki kararı
 * her zaman burada verilir.
 */
final readonly class RequireEntitlement
{
    public function __construct(private EntitlementRepositoryPort $entitlements) {}

    /** @throws EntitlementDeniedException */
    public function handle(int $workspaceId, Entitlement $entitlement): void
    {
        if (! $this->entitlements->forWorkspace($workspaceId)->grants($entitlement)) {
            throw EntitlementDeniedException::missing($entitlement);
        }
    }

    public function allows(int $workspaceId, Entitlement $entitlement): bool
    {
        return $this->entitlements->forWorkspace($workspaceId)->grants($entitlement);
    }
}
