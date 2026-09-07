<?php

declare(strict_types=1);

namespace App\Application\Billing\Port;

use App\Application\Billing\Dto\PlanSummary;

interface PlanCatalogRepositoryPort
{
    /**
     * @return list<PlanSummary>
     */
    public function listActivePlans(): array;

    /**
     * Tek bir planı id ile okur — YAYINDAN KALDIRILMIŞ OLSA BİLE.
     *
     * `listActivePlans` yalnız satılabilir kataloğu verir; oysa plan
     * DEĞİŞTİRME ekranı sahibin BUGÜN ödediği planı da anlatmak zorundadır
     * ve o plan katalogdan çekilmiş olabilir. Kataloğa düşülseydi, eski bir
     * plandaki sahip "neyi kaybedeceğini" hiç göremezdi.
     */
    public function findPlan(int $planId): ?PlanSummary;
}
