<?php

declare(strict_types=1);

namespace App\Application\Media\Port;

/**
 * Medya denetim izi yazıcısı — `docs/49` Faz 7 madde 4.
 *
 * Port, çünkü denetim izinin NEREYE yazıldığı bir altyapı kararıdır; hangi
 * eylemin ize değer olduğu ise uygulama kararıdır. İkisini aynı sınıfta
 * tutmak, izi bir gün başka bir yere taşımayı imkânsız kılardı.
 */
interface MediaAuditPort
{
    public function record(int $workspaceId, int $mediaAssetId, string $action, ?int $actorUserId): void;

    /**
     * Bir çalışma alanının son kayıtları — en yeni önce.
     *
     * @return array<int, array{id:int, mediaAssetId:int, action:string, actor:?string, at:?string}>
     */
    public function recent(int $workspaceId, int $limit = 50): array;
}
