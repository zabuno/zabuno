<?php

declare(strict_types=1);

namespace App\Application\Support\Port;

use App\Application\Support\Dto\SupportAccessSessionRow;

/**
 * Kiracıya HABER VERME — `docs/133` §3.
 *
 * "Kiracı sahibine hiç haber vermeden bakmak" bu paketin açıkça yasakladığı
 * kolaylıklardan biridir. Denetim kaydı sahibin panelinde durur; bu port,
 * sahibin panele bakmasını beklemeden ona haber verir.
 *
 * DÖNÜŞ ÜÇ DEĞERLİDİR (`docs/93` ile aynı sözleşme): `null` çıktı, bir dize
 * sebebi taşır, `false` hiç denenmedi demektir — ve denenmemişe damga
 * basılmaz.
 */
interface SupportAccessNotifierPort
{
    public function notifyWorkspaceOwners(SupportAccessSessionRow $session, string $workspaceName): string|false|null;
}
