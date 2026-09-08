<?php

declare(strict_types=1);

namespace App\Infrastructure\DataRights\Persistence;

use App\Application\DataRights\Dto\DataRequestRow;
use App\Application\DataRights\Port\DataRequestRepositoryPort;
use App\Domain\DataRights\DataRequestKind;
use App\Domain\DataRights\DataRequestState;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Defter tek tabloda — FF-226 (`docs/138`).
 *
 * SATIR ASLA SİLİNMEZ ve hiçbir yol `delete` çağırmaz: silme talebinin
 * kaydı, silmenin kendisinden sonra da yaşamalıdır.
 *
 * AKTÖR E-POSTAYLA okunur (`EloquentWorkspaceAuditTrail` ile aynı gerekçe):
 * bir ekipte iki "Mehmet" olabilir. Kullanıcı silinmişse alan boş kalır —
 * kaydı gizlemek yerine failin bilinmediğini söylemek dürüst olandır.
 */
final class EloquentDataRequestRepository implements DataRequestRepositoryPort
{
    public function open(
        int $workspaceId,
        DataRequestKind $kind,
        int $requestedByUserId,
        array $scopeSections,
        ?string $scheduledFor = null,
    ): DataRequestRow {
        $now = Carbon::now();

        $id = (int) DB::table('workspace_data_requests')->insertGetId([
            'workspace_id' => $workspaceId,
            'kind' => $kind->value,
            'state' => ($kind === DataRequestKind::Export ? DataRequestState::Queued : DataRequestState::Scheduled)->value,
            'requested_by_user_id' => $requestedByUserId,
            'requested_at' => $now,
            'scope' => json_encode(array_values($scopeSections), JSON_THROW_ON_ERROR),
            'scheduled_for' => $scheduledFor,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $row = $this->find($id);

        // `find` az önce yazılan satırı bulamıyorsa ortada bir veritabanı
        // arızası vardır; sessizce `null` döndürmek onu gizlerdi.
        assert($row !== null);

        return $row;
    }

    public function find(int $id): ?DataRequestRow
    {
        $row = $this->query()->where('r.id', $id)->first();

        return $row === null ? null : self::hydrate($row);
    }

    public function forWorkspace(int $workspaceId, int $limit = 20): array
    {
        return $this->query()
            ->where('r.workspace_id', $workspaceId)
            ->orderByDesc('r.requested_at')
            ->orderByDesc('r.id')
            ->limit($limit)
            ->get()
            ->map(static fn (object $row): DataRequestRow => self::hydrate($row))
            ->all();
    }

    public function openRequest(int $workspaceId, DataRequestKind $kind): ?DataRequestRow
    {
        $open = array_values(array_filter(
            DataRequestState::cases(),
            static fn (DataRequestState $state): bool => $state->isOpen(),
        ));

        $row = $this->query()
            ->where('r.workspace_id', $workspaceId)
            ->where('r.kind', $kind->value)
            ->whereIn('r.state', array_map(static fn (DataRequestState $state): string => $state->value, $open))
            ->orderByDesc('r.id')
            ->first();

        return $row === null ? null : self::hydrate($row);
    }

    public function markState(int $id, DataRequestState $state): void
    {
        DB::table('workspace_data_requests')->where('id', $id)->update([
            'state' => $state->value,
            'updated_at' => Carbon::now(),
        ]);
    }

    public function markExportReady(int $id, string $path, int $bytes, string $checksum, string $availableUntil): void
    {
        DB::table('workspace_data_requests')->where('id', $id)->update([
            'state' => DataRequestState::Ready->value,
            'artifact_path' => $path,
            'artifact_bytes' => $bytes,
            'artifact_checksum_sha256' => $checksum,
            'available_until' => $availableUntil,
            'completed_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    public function markFailed(int $id, string $reason): void
    {
        DB::table('workspace_data_requests')->where('id', $id)->update([
            'state' => DataRequestState::Failed->value,
            'failure_reason' => mb_substr($reason, 0, 190),
            'updated_at' => Carbon::now(),
        ]);
    }

    public function markCancelled(int $id, int $cancelledByUserId): void
    {
        DB::table('workspace_data_requests')->where('id', $id)->update([
            'state' => DataRequestState::Cancelled->value,
            'cancelled_at' => Carbon::now(),
            'cancelled_by_user_id' => $cancelledByUserId,
            'updated_at' => Carbon::now(),
        ]);
    }

    public function markCompleted(int $id, array $deletedCounts): void
    {
        DB::table('workspace_data_requests')->where('id', $id)->update([
            'state' => DataRequestState::Completed->value,
            'deleted_counts' => json_encode($deletedCounts, JSON_THROW_ON_ERROR),
            'completed_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    public function recordNotification(int $id, string|false|null $outcome): void
    {
        /*
            ÜÇ SONUÇ, ÜÇ AYRI YAZI (`docs/93`).

            `false` — hiç denenmedi (taşıyıcı yok ya da alıcı adresi yok).
            Damga ATILMAZ ve sebep de yazılmaz: bu bir arıza değildir.
            Yapılandırılmamış bir postaya "gönderildi" damgası basmak,
            kullanıcının gelmeyen bir e-postayı beklemesi demekti.

            dize — denendi, düştü. Sebep satıra yazılır, damga atılmaz.
            `null` — gitti. Damga atılır.
        */
        if ($outcome === false) {
            return;
        }

        DB::table('workspace_data_requests')->where('id', $id)->update(
            $outcome === null
                ? ['notified_at' => Carbon::now(), 'notification_failure' => null, 'updated_at' => Carbon::now()]
                : ['notification_failure' => mb_substr($outcome, 0, 190), 'updated_at' => Carbon::now()],
        );
    }

    public function artifactPath(int $id): ?string
    {
        $path = DB::table('workspace_data_requests')->where('id', $id)->value('artifact_path');

        return is_string($path) && $path !== '' ? $path : null;
    }

    public function expiredExports(): array
    {
        return $this->query()
            ->where('r.kind', DataRequestKind::Export->value)
            ->where('r.state', DataRequestState::Ready->value)
            ->whereNotNull('r.available_until')
            ->where('r.available_until', '<=', Carbon::now())
            ->get()
            ->map(static fn (object $row): DataRequestRow => self::hydrate($row))
            ->all();
    }

    public function dueErasures(): array
    {
        return $this->query()
            ->where('r.kind', DataRequestKind::Erasure->value)
            ->where('r.state', DataRequestState::Scheduled->value)
            ->whereNotNull('r.scheduled_for')
            ->where('r.scheduled_for', '<=', Carbon::now())
            ->orderBy('r.id')
            ->get()
            ->map(static fn (object $row): DataRequestRow => self::hydrate($row))
            ->all();
    }

    private function query(): Builder
    {
        return DB::table('workspace_data_requests as r')
            ->leftJoin('users as u', 'u.id', '=', 'r.requested_by_user_id')
            ->select([
                'r.id', 'r.workspace_id', 'r.kind', 'r.state', 'r.requested_at', 'r.scope',
                'r.artifact_bytes', 'r.available_until', 'r.scheduled_for', 'r.completed_at',
                'r.cancelled_at', 'r.failure_reason', 'r.notified_at', 'r.notification_failure',
                'r.deleted_counts', 'u.email',
            ]);
    }

    private static function hydrate(object $row): DataRequestRow
    {
        /** @var list<string> $scope */
        $scope = json_decode((string) $row->scope, true, 512, JSON_THROW_ON_ERROR);
        /** @var array<string, int> $counts */
        $counts = $row->deleted_counts === null
            ? []
            : json_decode((string) $row->deleted_counts, true, 512, JSON_THROW_ON_ERROR);

        return new DataRequestRow(
            id: (int) $row->id,
            workspaceId: (int) $row->workspace_id,
            kind: DataRequestKind::from((string) $row->kind),
            state: DataRequestState::from((string) $row->state),
            requestedByEmail: $row->email === null ? null : (string) $row->email,
            requestedAt: (string) $row->requested_at,
            scopeSections: $scope,
            artifactBytes: $row->artifact_bytes === null ? null : (int) $row->artifact_bytes,
            availableUntil: $row->available_until === null ? null : (string) $row->available_until,
            scheduledFor: $row->scheduled_for === null ? null : (string) $row->scheduled_for,
            completedAt: $row->completed_at === null ? null : (string) $row->completed_at,
            cancelledAt: $row->cancelled_at === null ? null : (string) $row->cancelled_at,
            failureReason: $row->failure_reason === null ? null : (string) $row->failure_reason,
            notifiedAt: $row->notified_at === null ? null : (string) $row->notified_at,
            notificationFailure: $row->notification_failure === null ? null : (string) $row->notification_failure,
            deletedCounts: $counts,
        );
    }
}
