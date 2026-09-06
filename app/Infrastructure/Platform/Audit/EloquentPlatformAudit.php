<?php

declare(strict_types=1);

namespace App\Infrastructure\Platform\Audit;

use App\Application\Platform\Port\PlatformAuditPort;
use Illuminate\Support\Facades\DB;

/** Append-only; `updated_at` yok — satır bir kez yazılır, değişmez. */
final class EloquentPlatformAudit implements PlatformAuditPort
{
    public function record(string $scope, string $action, ?string $subject, array $details = [], ?int $actorUserId = null): void
    {
        DB::table('platform_audits')->insert([
            'scope' => $scope,
            'action' => $action,
            'subject' => $subject,
            'details' => $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'actor_user_id' => $actorUserId,
            'created_at' => now(),
        ]);
    }
}
