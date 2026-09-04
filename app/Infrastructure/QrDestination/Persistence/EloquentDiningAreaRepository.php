<?php

declare(strict_types=1);

namespace App\Infrastructure\QrDestination\Persistence;

use App\Application\QrDestination\Port\DiningAreaRepositoryPort;
use Illuminate\Support\Facades\DB;

final class EloquentDiningAreaRepository implements DiningAreaRepositoryPort
{
    /** @return list<array{id: int, label: string, tableCount: int}> */
    public function listForLocation(int $workspaceId, int $locationId): array
    {
        return DB::table('dining_areas')
            ->leftJoin('dining_tables', 'dining_tables.area_id', '=', 'dining_areas.id')
            ->where('dining_areas.workspace_id', $workspaceId)
            ->where('dining_areas.location_id', $locationId)
            ->groupBy('dining_areas.id', 'dining_areas.label')
            ->orderBy('dining_areas.id')
            ->select([
                'dining_areas.id as id',
                'dining_areas.label as label',
                // Masa sayısı YAZILIR: "Bahçe (12 masa)" adı yeniden
                // adlandırırken hangi alan olduğunu hatırlatır.
                DB::raw('COUNT(dining_tables.id) as table_count'),
            ])
            ->get()
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'label' => (string) $row->label,
                'tableCount' => (int) $row->table_count,
            ])
            ->all();
    }

    public function belongsToLocation(int $areaId, int $workspaceId, int $locationId): bool
    {
        return DB::table('dining_areas')
            ->where('id', $areaId)
            ->where('workspace_id', $workspaceId)
            ->where('location_id', $locationId)
            ->exists();
    }

    public function rename(int $areaId, string $label): void
    {
        DB::table('dining_areas')->where('id', $areaId)->update([
            'label' => $label,
            'updated_at' => now(),
        ]);
    }
}
