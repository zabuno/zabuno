<?php

declare(strict_types=1);

namespace App\Infrastructure\Entitlement;

use App\Application\Entitlement\Port\EntitlementRepositoryPort;
use App\Domain\Entitlement\EntitlementSet;
use Illuminate\Support\Facades\DB;

/**
 * Abonelik → plan → entitlements zincirini okur.
 *
 * `plans.entitlements` serbest string listesi tutar; tanınmayan anahtarlar
 * `EntitlementSet` kurulumunda düşürülür.
 */
final class DatabaseEntitlementRepository implements EntitlementRepositoryPort
{
    /** Yetenek veren abonelik durumları. Diğer her durum boş küme demektir. */
    private const GRANTING_STATES = ['active', 'trialing'];

    public function forWorkspace(int $workspaceId): EntitlementSet
    {
        $row = DB::table('subscriptions')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('subscriptions.workspace_id', $workspaceId)
            ->whereIn('subscriptions.state', self::GRANTING_STATES)
            ->where('plans.is_active', true)
            ->select(['subscriptions.ends_at', 'plans.entitlements'])
            ->first();

        if ($row === null) {
            return EntitlementSet::empty();
        }

        // Süresi dolmuş bir abonelik yetenek vermez; durum alanı geç
        // güncellenmiş olabilir ve tarih daha güvenilir bir kanıttır.
        if ($row->ends_at !== null && strtotime((string) $row->ends_at) < time()) {
            return EntitlementSet::empty();
        }

        $decoded = json_decode((string) $row->entitlements, true);

        return is_array($decoded)
            ? EntitlementSet::fromKeys(array_values(array_filter($decoded, 'is_string')))
            : EntitlementSet::empty();
    }
}
