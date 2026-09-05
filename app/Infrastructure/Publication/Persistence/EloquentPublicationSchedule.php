<?php

declare(strict_types=1);

namespace App\Infrastructure\Publication\Persistence;

use App\Application\Publication\Dto\ScheduledPublicationRecord;
use App\Application\Publication\Port\PublicationSchedulePort;
use App\Domain\Publication\ScheduledPublicationState;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class EloquentPublicationSchedule implements PublicationSchedulePort
{
    public function schedule(
        int $workspaceId,
        int $menuId,
        int $locationId,
        CarbonInterface $scheduledFor,
        array $snapshot,
        array $visibleItemIds,
        ?int $brandId,
        int $scheduledByUserId,
    ): ScheduledPublicationRecord {
        return DB::transaction(function () use (
            $workspaceId,
            $menuId,
            $locationId,
            $scheduledFor,
            $snapshot,
            $visibleItemIds,
            $brandId,
            $scheduledByUserId,
        ): ScheduledPublicationRecord {
            /*
                Önceki bekleyen plan İPTAL EDİLİR, üstüne ikinci bir plan
                yazılmaz. Aksi hâlde fikrini değiştiren sahip iki yayın
                kurmuş olurdu: menü gece 03:00'te bir kez, Pazartesi 09:00'da
                bir kez daha değişir ve ikincisini kimse beklemezdi.
            */
            DB::table('menu_publication_schedules')
                ->where('menu_id', $menuId)
                ->where('state', ScheduledPublicationState::Pending->value)
                ->update([
                    'state' => ScheduledPublicationState::Cancelled->value,
                    'updated_at' => now(),
                ]);

            $id = (int) DB::table('menu_publication_schedules')->insertGetId([
                'workspace_id' => $workspaceId,
                'menu_id' => $menuId,
                'location_id' => $locationId,
                'scheduled_for' => $scheduledFor->clone()->utc(),
                'state' => ScheduledPublicationState::Pending->value,
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'visible_item_ids' => json_encode(array_values($visibleItemIds), JSON_THROW_ON_ERROR),
                'brand_id' => $brandId,
                'scheduled_by' => $scheduledByUserId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return new ScheduledPublicationRecord(
                $id,
                $workspaceId,
                $menuId,
                $locationId,
                $scheduledFor->clone()->utc()->toISOString(),
                ScheduledPublicationState::Pending->value,
                $snapshot,
                array_values($visibleItemIds),
                $brandId,
                $scheduledByUserId,
            );
        });
    }

    public function pendingForMenu(int $workspaceId, int $menuId): ?ScheduledPublicationRecord
    {
        $row = DB::table('menu_publication_schedules')
            ->where('workspace_id', $workspaceId)
            ->where('menu_id', $menuId)
            ->where('state', ScheduledPublicationState::Pending->value)
            ->orderByDesc('id')
            ->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function cancel(int $workspaceId, int $menuId, int $scheduleId): bool
    {
        return DB::table('menu_publication_schedules')
            ->where('id', $scheduleId)
            ->where('workspace_id', $workspaceId)
            ->where('menu_id', $menuId)
            ->where('state', ScheduledPublicationState::Pending->value)
            ->update([
                'state' => ScheduledPublicationState::Cancelled->value,
                'updated_at' => now(),
            ]) === 1;
    }

    public function due(CarbonInterface $now): array
    {
        return DB::table('menu_publication_schedules')
            ->where('state', ScheduledPublicationState::Pending->value)
            ->where('scheduled_for', '<=', $now->clone()->utc())
            ->orderBy('scheduled_for')
            ->get()
            ->map(fn (object $row): ScheduledPublicationRecord => $this->hydrate($row))
            ->all();
    }

    public function claim(int $scheduleId): bool
    {
        /*
            Sahiplenme TEK BİR ATOMİK GÜNCELLEMEDİR ve koşul cümlesinin
            içindedir. Önce `SELECT` edip sonra `UPDATE` etseydik, iki koşu
            arasındaki milisaniyede ikisi de "bekliyor" görür ve menü aynı
            gece iki kez yayınlanırdı.
        */
        return DB::table('menu_publication_schedules')
            ->where('id', $scheduleId)
            ->where('state', ScheduledPublicationState::Pending->value)
            ->update([
                'state' => ScheduledPublicationState::Publishing->value,
                'updated_at' => now(),
            ]) === 1;
    }

    public function markPublished(int $scheduleId, int $publicationId): void
    {
        DB::table('menu_publication_schedules')
            ->where('id', $scheduleId)
            ->update([
                'state' => ScheduledPublicationState::Published->value,
                'publication_id' => $publicationId,
                'updated_at' => now(),
            ]);
    }

    public function markFailed(int $scheduleId): void
    {
        DB::table('menu_publication_schedules')
            ->where('id', $scheduleId)
            ->update([
                'state' => ScheduledPublicationState::Failed->value,
                'updated_at' => now(),
            ]);
    }

    private function hydrate(object $row): ScheduledPublicationRecord
    {
        /** @var array<string,mixed> $snapshot */
        $snapshot = json_decode((string) $row->snapshot, true, 512, JSON_THROW_ON_ERROR);
        /** @var list<int> $visibleItemIds */
        $visibleItemIds = array_map(
            'intval',
            (array) json_decode((string) $row->visible_item_ids, true, 512, JSON_THROW_ON_ERROR)
        );

        return new ScheduledPublicationRecord(
            (int) $row->id,
            (int) $row->workspace_id,
            (int) $row->menu_id,
            (int) $row->location_id,
            Carbon::parse($row->scheduled_for)->utc()->toISOString(),
            (string) $row->state,
            $snapshot,
            array_values($visibleItemIds),
            $row->brand_id === null ? null : (int) $row->brand_id,
            (int) $row->scheduled_by,
        );
    }
}
