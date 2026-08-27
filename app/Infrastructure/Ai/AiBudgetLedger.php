<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai;

use Illuminate\Support\Facades\DB;

/**
 * Tenant başına aylık AI bütçesi.
 *
 * Harcama denetim kaydından TÜRETİLİR, ayrı bir sayaçtan değil. Ayrı sayaç
 * tutmak, iki kaynağın bir gün ayrışması demektir — ve ayrıştığında hangisinin
 * doğru olduğu bilinemez.
 */
final readonly class AiBudgetLedger
{
    public function monthlyLimitMinor(): int
    {
        return (int) config('ai.budget.monthly_minor_per_tenant', 0);
    }

    public function spentThisMonthMinor(int $workspaceId): int
    {
        return (int) DB::table('ai_invocations')
            ->where('workspace_id', $workspaceId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('cost_minor');
    }

    public function hasRemaining(int $workspaceId): bool
    {
        $limit = $this->monthlyLimitMinor();

        // Sıfır limit "sınırsız" DEĞİL "kapalı" demektir. Varsayılan olarak
        // sınırsız harcama açmak, tavansız bir maliyet demek olurdu.
        if ($limit <= 0) {
            return false;
        }

        return $this->spentThisMonthMinor($workspaceId) < $limit;
    }
}
