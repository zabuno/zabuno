<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * AI denetim izi — `docs/98` Tur 3'ün iki ekransız tablosu.
 *
 * `platform_credential_audits` (kim hangi anahtarı ne zaman yazdı/kapattı,
 * hangi hesap sağlıksız düştü) ve `ai_connection_assignments` (hangi tenant
 * hangi hesaba yapıştı) 2026-09-03'ten beri doluyordu; okuyan ekran yoktu.
 * Denetim izi okunmuyorsa yoktur.
 *
 * SIR YOK: bu tablolar zaten sır taşımaz (`docs/94`); cevap da taşımaz.
 * Yalnız superadmin, yalnız son 200 satır — daha eskisi için sunucu.
 */
final class ShowAiAuditController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $audits = DB::table('platform_credential_audits as a')
            ->leftJoin('platform_credential_connections as c', 'c.id', '=', 'a.connection_id')
            ->leftJoin('users as u', 'u.id', '=', 'a.actor_user_id')
            ->orderByDesc('a.id')
            ->limit(200)
            ->get(['a.id', 'a.provider', 'a.connection_id', 'c.label as connection_label', 'a.action', 'u.name as actor_name', 'a.created_at'])
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'provider' => (string) $row->provider,
                'connectionId' => $row->connection_id === null ? null : (int) $row->connection_id,
                'connectionLabel' => $row->connection_label,
                'action' => (string) $row->action,
                // Aktör yoksa "sunucu": komut satırından yazılan kayıt kimse
                // adına değildir ve bir isim uydurulmaz.
                'actor' => $row->actor_name,
                'at' => (string) $row->created_at,
            ])
            ->all();

        $assignments = DB::table('ai_connection_assignments as s')
            ->join('workspaces as w', 'w.id', '=', 's.workspace_id')
            ->join('platform_credential_connections as c', 'c.id', '=', 's.connection_id')
            ->orderBy('w.name')
            ->orderBy('s.provider')
            ->get(['s.workspace_id', 'w.name as workspace_name', 's.provider', 's.connection_id', 'c.label as connection_label', 'c.health_status', 's.updated_at'])
            ->map(static fn (object $row): array => [
                'workspaceId' => (int) $row->workspace_id,
                'workspaceName' => (string) $row->workspace_name,
                'provider' => (string) $row->provider,
                'connectionId' => (int) $row->connection_id,
                'connectionLabel' => (string) $row->connection_label,
                'health' => (string) $row->health_status,
                'since' => (string) $row->updated_at,
            ])
            ->all();

        return response()->json(['audits' => $audits, 'assignments' => $assignments]);
    }
}
