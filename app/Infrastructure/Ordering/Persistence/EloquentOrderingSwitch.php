<?php

declare(strict_types=1);

namespace App\Infrastructure\Ordering\Persistence;

use App\Application\Ordering\Port\OrderingSwitchPort;
use Illuminate\Support\Facades\DB;

final class EloquentOrderingSwitch implements OrderingSwitchPort
{
    public function acceptsOrders(int $workspaceId, int $locationId): bool
    {
        /*
            KİRACI KOŞULU SORGUNUN İÇİNDE.

            Yalnız `id` ile sorulsaydı, başka bir kiracının şubesinin
            şalteri okunabilirdi. Burada okunan değer bir siparişin
            yazılıp yazılmayacağına karar veriyor; kapsam koşulunu
            çağırana bırakmak, bir gün birinin unutmasını beklemektir.

            Şube bulunamazsa `false`: var olmayan bir şubenin sipariş
            aldığını varsaymak, kapalı saymaktan her zaman kötüdür.
        */
        return (bool) DB::table('locations')
            ->where('id', $locationId)
            ->where('workspace_id', $workspaceId)
            ->value('accepts_orders');
    }
}
