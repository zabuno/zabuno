<?php

declare(strict_types=1);

namespace App\Infrastructure\Support\Persistence;

use App\Application\Support\Dto\SupportAccessSessionRow;
use App\Application\Support\Port\SupportAccessPort;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Kiracı olarak bakma oturumlarının deposu — `docs/133`.
 *
 * SÜRE DOLMASI BİR İŞ DEĞİL, BİR SORGUDUR. Oturumu kapatan bir kuyruk
 * işçisi, bir zamanlayıcı ya da bir cron YOKTUR: "açık mı?" sorusu her
 * istekte `expires_at > now` ile sorulur. Zamanlayıcıya bağlansaydı,
 * zamanlayıcının çalışmadığı bir kurulumda oturum sessizce açık kalırdı —
 * ve "çıkış yapmayı unuttu" kusurunu tam olarak geri getirirdi.
 */
final class EloquentSupportAccess implements SupportAccessPort
{
    public function activeFor(int $userId): ?SupportAccessSessionRow
    {
        $now = Carbon::now();

        $row = DB::table('support_access_sessions as s')
            ->leftJoin('users as u', 'u.id', '=', 's.actor_user_id')
            ->where('s.actor_user_id', $userId)
            ->whereNull('s.ended_at')
            ->where('s.expires_at', '>', $now)
            ->orderByDesc('s.id')
            ->first($this->columns());

        return $row === null ? null : $this->toRow($row, $now);
    }

    public function open(int $workspaceId, int $actorUserId, string $reason, int $minutes): SupportAccessSessionRow
    {
        $now = Carbon::now();
        $expiresAt = $now->copy()->addMinutes($minutes);

        $id = (int) DB::table('support_access_sessions')->insertGetId([
            'workspace_id' => $workspaceId,
            'actor_user_id' => $actorUserId,
            'reason' => $reason,
            'started_at' => $now,
            'expires_at' => $expiresAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $row = DB::table('support_access_sessions as s')
            ->leftJoin('users as u', 'u.id', '=', 's.actor_user_id')
            ->where('s.id', $id)
            ->first($this->columns());

        // Az önce yazılan satır okunamıyorsa depo arızalıdır; uydurulmuş
        // bir dönüş, olmayan bir oturumu var gibi gösterirdi.
        if ($row === null) {
            throw new \RuntimeException('Support access session could not be read back after insert.');
        }

        return $this->toRow($row, $now);
    }

    public function end(int $sessionId, int $actorUserId): bool
    {
        $now = Carbon::now();

        /*
            BİTİŞİ YALNIZ AÇAN KAPATIR ve yalnız AÇIK bir oturum kapanır.
            `whereNull('ended_at')` olmasaydı ikinci bir çağrı damgayı ileri
            kaydırır, oturumun ne kadar sürdüğünü kiracıya yanlış söylerdi.
        */
        $affected = DB::table('support_access_sessions')
            ->where('id', $sessionId)
            ->where('actor_user_id', $actorUserId)
            ->whereNull('ended_at')
            ->update(['ended_at' => $now, 'updated_at' => $now]);

        return $affected > 0;
    }

    public function forWorkspace(int $workspaceId, int $limit = 20): array
    {
        $now = Carbon::now();

        return DB::table('support_access_sessions as s')
            ->leftJoin('users as u', 'u.id', '=', 's.actor_user_id')
            ->where('s.workspace_id', $workspaceId)
            ->orderByDesc('s.started_at')
            ->orderByDesc('s.id')
            ->limit($limit)
            ->get($this->columns())
            ->map(fn (object $row): SupportAccessSessionRow => $this->toRow($row, $now))
            ->all();
    }

    public function recordOwnerNotification(int $sessionId, string|false|null $failure): void
    {
        // `false` = hiç denenmedi. Günlüğe yazılmış ya da hiç yazılmamış bir
        // e-postaya "gönderildi" demek, `docs/93`'te reddedilen yalandır.
        if ($failure === false) {
            return;
        }

        DB::table('support_access_sessions')
            ->where('id', $sessionId)
            ->update([
                'notified_at' => $failure === null ? Carbon::now() : null,
                'notification_failure' => $failure,
                'updated_at' => Carbon::now(),
            ]);
    }

    /** @return list<string> */
    private function columns(): array
    {
        return [
            's.id',
            's.workspace_id',
            's.reason',
            's.started_at',
            's.expires_at',
            's.ended_at',
            'u.email',
        ];
    }

    private function toRow(object $row, Carbon $now): SupportAccessSessionRow
    {
        $expiresAt = Carbon::parse((string) $row->expires_at);
        $endedAt = $row->ended_at === null ? null : (string) $row->ended_at;

        return new SupportAccessSessionRow(
            (int) $row->id,
            (int) $row->workspace_id,
            $row->email === null ? null : (string) $row->email,
            (string) $row->reason,
            (string) $row->started_at,
            (string) $row->expires_at,
            $endedAt,
            $endedAt === null && $expiresAt->greaterThan($now),
        );
    }
}
