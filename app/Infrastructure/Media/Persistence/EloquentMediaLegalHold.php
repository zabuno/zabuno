<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Persistence;

use App\Application\Media\Port\MediaLegalHoldPort;
use Illuminate\Support\Facades\DB;

/**
 * `MediaLegalHoldPort`in SQL karşılığı.
 *
 * `withTrashed` yok, çünkü ham sorgu Eloquent'in yumuşak silme kapsamını
 * hiç uygulamaz: çöpteki bir dosyanın kilidi de görünür — ve görünmeliydi,
 * çünkü kalıcı silme tam olarak orada durdurulur.
 */
final class EloquentMediaLegalHold implements MediaLegalHoldPort
{
    private const TABLE = 'media_assets';

    public function isHeld(int $workspaceId, int $assetId): bool
    {
        return DB::table(self::TABLE)
            ->where('workspace_id', $workspaceId)
            ->where('id', $assetId)
            ->whereNotNull('legal_hold_at')
            ->exists();
    }

    public function set(int $workspaceId, int $assetId, ?string $reason, ?int $actorUserId): bool
    {
        $trimmed = $reason === null ? null : trim($reason);
        $placing = $trimmed !== null && $trimmed !== '';

        return DB::table(self::TABLE)
            ->where('workspace_id', $workspaceId)
            ->where('id', $assetId)
            ->update([
                // Sebep kilitle BİRLİKTE yaşar: kilidi kaldırırken sebebi
                // bırakmak, altı ay sonra "bu neden yazıyor?" diye
                // sorulacak ölü bir cümle bırakırdı.
                'legal_hold_reason' => $placing ? $trimmed : null,
                'legal_hold_at' => $placing ? now() : null,
                'legal_hold_by' => $placing ? $actorUserId : null,
                'updated_at' => now(),
            ]) === 1;
    }

    /** @return list<array{id:int, name:string, reason:string, at:?string}> */
    public function all(int $workspaceId): array
    {
        return DB::table(self::TABLE)
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('legal_hold_at')
            ->orderByDesc('legal_hold_at')
            ->get(['id', 'display_name', 'original_name', 'legal_hold_reason', 'legal_hold_at'])
            ->map(static function (object $row): array {
                $name = (string) ($row->display_name ?? '');

                return [
                    'id' => (int) $row->id,
                    'name' => $name === '' ? (string) $row->original_name : $name,
                    'reason' => (string) $row->legal_hold_reason,
                    'at' => $row->legal_hold_at === null ? null : (string) $row->legal_hold_at,
                ];
            })->values()->all();
    }
}
