<?php

declare(strict_types=1);

namespace App\Application\DataRights\Port;

use App\Application\DataRights\Dto\DataRequestRow;

/**
 * Haber verme — FF-226, `docs/93` deseni.
 *
 * İstisna FIRLATMAZ. Gönderim kaydın şartı değil yardımcısıdır: taşıyıcı
 * yoksa ya da düşerse talep durur, ekranda görünür ve sebebi satıra
 * yazılır. Aksi hâlde bir posta arızası, kullanıcının arşivini yok ederdi.
 *
 * @return string|false|null `null` devralındı, dize sebep, `false` hiç denenmedi
 */
interface DataRightsNotifierPort
{
    public function notify(DataRequestRow $request, string $recipientEmail, ?string $downloadUrl): string|false|null;
}
