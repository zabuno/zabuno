<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Entitlement\Entitlement;
use Illuminate\Support\Facades\DB;

/**
 * Bir workspace'e plan yeteneği verir (CORE-04).
 *
 * Owner 2026-08-26'da toplu QR üretimi, ekip daveti ve analitik raporlamayı
 * plana bağlama kararı verdi. Bu yetenekleri kullanan testler yazıldıklarında
 * hepsi ücretsizdi; artık açıkça plan kurmaları gerekiyor.
 *
 * Paylaşılan bir yardımcı olmasının sebebi tek satır tasarrufu değil: her test
 * dosyasının kendi plan/abonelik kurgusunu yazması, ilerideki bir şema
 * değişikliğinde onlarca yerde aynı düzeltmeyi gerektirirdi.
 */
trait GrantsPlanEntitlements
{
    /** @param list<Entitlement>|null $entitlements null ise BİLİNEN her yetenek verilir */
    private function grantEntitlements(int $workspaceId, ?array $entitlements = null): void
    {
        $keys = array_map(
            static fn (Entitlement $entitlement): string => $entitlement->value,
            $entitlements ?? Entitlement::cases(),
        );

        $planId = (int) DB::table('plans')->insertGetId([
            'name' => 'Test Plan',
            'code' => 'test-'.uniqid(),
            'version' => 1,
            'is_active' => true,
            'sort_order' => 0,
            'entitlements' => json_encode(array_values($keys)),
            'amount_minor' => 0,
            'currency' => 'TRY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // `workspace_id` tekildir: ikinci abonelik eklemek yerine güncellenir.
        DB::table('subscriptions')->updateOrInsert(
            ['workspace_id' => $workspaceId],
            [
                'plan_id' => $planId,
                'state' => 'active',
                'ends_at' => now()->addYear(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
