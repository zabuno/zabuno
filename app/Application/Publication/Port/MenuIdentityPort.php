<?php

declare(strict_types=1);

namespace App\Application\Publication\Port;

use App\Domain\Publication\MenuIdentity;

/**
 * Yayın anında dondurulacak restoran kimliğini okur (`docs/75`).
 *
 * Bu port YALNIZ yayın anında çağrılır. Misafir sayfası kimliği canlı
 * sorgudan değil, snapshot'tan okur.
 */
interface MenuIdentityPort
{
    public function forMenu(int $workspaceId, int $menuId): ?MenuIdentity;

    /** Logonun bağlı olduğu marka; menüden şubeye, şubeden markaya. */
    public function brandIdForMenu(int $workspaceId, int $menuId): ?int;
}
