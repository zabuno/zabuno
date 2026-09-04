<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Persistence;

use App\Application\Media\Port\MediaAuditPort;
use Illuminate\Support\Facades\DB;

final class EloquentMediaAudit implements MediaAuditPort
{
    private const TABLE = 'media_audits';

    public function record(int $workspaceId, int $mediaAssetId, string $action, ?int $actorUserId): void
    {
        DB::table(self::TABLE)->insert([
            'workspace_id' => $workspaceId,
            'media_asset_id' => $mediaAssetId,
            'action' => $action,
            'actor_user_id' => $actorUserId,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<int, array{id:int, mediaAssetId:int, action:string, actor:?string, at:?string}>
     */
    public function recent(int $workspaceId, int $limit = 50): array
    {
        /*
            Aktörün ADI değil E-POSTASI okunur: bir ekipte iki "Mehmet"
            olabilir ve denetim izinde "Mehmet sildi" cümlesi hiçbir soruyu
            kapatmaz. Kullanıcı silinmişse alan boş kalır — kaydı silmek
            yerine failin bilinmediğini söylemek dürüst olandır.
        */
        $rows = DB::table(self::TABLE.' as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.actor_user_id')
            ->where('a.workspace_id', $workspaceId)
            ->orderByDesc('a.created_at')
            ->orderByDesc('a.id')
            ->limit($limit)
            ->get(['a.id', 'a.media_asset_id', 'a.action', 'a.created_at', 'u.email']);

        return $rows->map(static fn (object $row): array => [
            'id' => (int) $row->id,
            'mediaAssetId' => (int) $row->media_asset_id,
            'action' => (string) $row->action,
            'actor' => $row->email === null ? null : (string) $row->email,
            'at' => $row->created_at === null ? null : (string) $row->created_at,
        ])->all();
    }
}
