<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Persistence;

use App\Application\Billing\Dto\PlanSummary;
use App\Application\Billing\Port\PlanCatalogRepositoryPort;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EloquentPlanCatalogRepository implements PlanCatalogRepositoryPort
{
    public function listActivePlans(): array
    {
        $rows = DB::table('plans')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $rows->map(fn (object $row): PlanSummary => $this->toSummary($row))->all();
    }

    private function toSummary(object $row): PlanSummary
    {
        $id = (int) $row->id;

        if ($id <= 0) {
            throw new RuntimeException("Plan row has a non-positive id [{$row->id}].");
        }

        $version = (int) $row->version;

        if ($version <= 0) {
            throw new RuntimeException("Plan [{$id}] has a non-positive version.");
        }

        $entitlements = json_decode((string) $row->entitlements, true);

        if (! is_array($entitlements) || ! array_is_list($entitlements)) {
            throw new RuntimeException("Plan [{$id}] entitlements are not a JSON list.");
        }

        foreach ($entitlements as $entitlement) {
            if (! is_string($entitlement)) {
                throw new RuntimeException("Plan [{$id}] entitlements must all be strings.");
            }
        }

        $amountMinor = $row->amount_minor;
        $currency = $row->currency;

        if ($amountMinor !== null) {
            if (! is_numeric($amountMinor) || (int) $amountMinor !== (int) (string) $amountMinor || (int) $amountMinor < 0) {
                throw new RuntimeException("Plan [{$id}] amount_minor must be a non-negative integer.");
            }

            $amountMinor = (int) $amountMinor;
        }

        if ($currency !== null) {
            $currency = (string) $currency;

            if (strlen($currency) !== 3 || $currency !== strtoupper($currency) || ! ctype_alpha($currency)) {
                throw new RuntimeException("Plan [{$id}] currency must be an uppercase 3-letter code.");
            }
        }

        if (($amountMinor === null) !== ($currency === null)) {
            throw new RuntimeException("Plan [{$id}] amount_minor and currency must be paired.");
        }

        return new PlanSummary(
            $id,
            (string) $row->name,
            (string) $row->code,
            $entitlements,
            $amountMinor,
            $currency,
        );
    }
}
