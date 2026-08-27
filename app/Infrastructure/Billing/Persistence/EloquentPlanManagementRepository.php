<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Persistence;

use App\Application\Billing\Dto\ManagedPlan;
use App\Application\Billing\Exception\PlanVersionConflictException;
use App\Application\Billing\Port\PlanManagementRepositoryPort;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class EloquentPlanManagementRepository implements PlanManagementRepositoryPort
{
    public function listAllVersions(): array
    {
        $rows = DB::table('plans')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $rows->map(fn (object $row): ManagedPlan => $this->toManagedPlan($row))->all();
    }

    public function createInactive(
        string $name,
        string $code,
        int $version,
        array $entitlements,
        ?int $amountMinor,
        ?string $currency,
        int $sortOrder,
    ): ManagedPlan {
        $row = [
            'name' => $name,
            'code' => $code,
            'version' => $version,
            'is_active' => false,
            'sort_order' => $sortOrder,
            'entitlements' => json_encode($entitlements),
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        try {
            // İç içe işlem = SAVEPOINT. PostgreSQL'de başarısız bir INSERT
            // içinde bulunduğu işlemin TAMAMINI zehirler (SQLSTATE 25P02):
            // sonraki her sorgu, işlem kapanana kadar reddedilir. SQLite
            // böyle davranmadığı için bu desen yıllarca çalışıyor göründü.
            // Savepoint, başarısızlığı yalnız kendi kapsamına geri sarar.
            $id = DB::transaction(
                static fn () => DB::table('plans')->insertGetId($row)
            );
        } catch (QueryException $exception) {
            // SQLSTATE motora göre değişir: MySQL/SQLite bütünlük ihlali
            // için 23000, PostgreSQL benzersizlik ihlali için 23505 verir.
            // Yalnız 23000 aranırsa PostgreSQL'de temiz bir 422 yerine ham
            // bir 500 döner — yani hata mesajı motor değişince kaybolur.
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)
                && $this->isCodeVersionUniqueViolation($exception)) {
                throw new PlanVersionConflictException('A plan with this code and version already exists.', previous: $exception);
            }

            throw $exception;
        }

        $row = DB::table('plans')->where('id', $id)->first();

        return $this->toManagedPlan($row);
    }

    public function activate(int $planId): ?ManagedPlan
    {
        return DB::transaction(function () use ($planId): ?ManagedPlan {
            $target = DB::table('plans')->where('id', $planId)->lockForUpdate()->first();

            if ($target === null) {
                return null;
            }

            DB::table('plans')
                ->where('code', $target->code)
                ->where('id', '!=', $target->id)
                ->lockForUpdate()
                ->update(['is_active' => false, 'updated_at' => now()]);

            DB::table('plans')
                ->where('id', $target->id)
                ->update(['is_active' => true, 'updated_at' => now()]);

            $updated = DB::table('plans')->where('id', $target->id)->first();

            return $this->toManagedPlan($updated);
        });
    }

    private function isCodeVersionUniqueViolation(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'plans_code_version_unique')
            || str_contains($message, 'UNIQUE constraint failed: plans.code, plans.version');
    }

    private function toManagedPlan(object $row): ManagedPlan
    {
        $entitlements = json_decode((string) $row->entitlements, true);

        if (($row->amount_minor === null) !== ($row->currency === null)) {
            throw new \RuntimeException("Plan {$row->id} has a malformed amount_minor/currency pair: exactly one of the pair is null.");
        }

        return new ManagedPlan(
            (int) $row->id,
            (string) $row->name,
            (string) $row->code,
            (int) $row->version,
            is_array($entitlements) ? array_values(array_map('strval', $entitlements)) : [],
            $row->amount_minor === null ? null : (int) $row->amount_minor,
            $row->currency === null ? null : (string) $row->currency,
            (int) $row->sort_order,
            (bool) $row->is_active,
        );
    }
}
