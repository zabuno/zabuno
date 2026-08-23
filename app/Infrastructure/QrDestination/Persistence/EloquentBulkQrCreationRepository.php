<?php

declare(strict_types=1);

namespace App\Infrastructure\QrDestination\Persistence;

use App\Application\QrDestination\Dto\BulkQrCreationResult;
use App\Application\QrDestination\Dto\DiningAreaRecord;
use App\Application\QrDestination\Dto\DiningTableRecord;
use App\Application\QrDestination\Dto\QrCodeRecord;
use App\Application\QrDestination\Exception\BulkQrCreationFailedException;
use App\Application\QrDestination\Port\BulkQrCreationPort;
use App\Domain\QrDestination\QrCodeState;
use App\Domain\QrDestination\QrToken;
use Illuminate\Support\Facades\DB;
use Throwable;

final class EloquentBulkQrCreationRepository implements BulkQrCreationPort
{
    private const DESTINATION_TYPE_PUBLISHED_MENU = 'published_menu';

    public function create(
        int $workspaceId,
        int $locationId,
        int $menuId,
        array $areaLabels,
        array $tableNames,
        int $seatCountPerTable,
        callable $isTokenTaken,
    ): BulkQrCreationResult {
        try {
            return DB::transaction(function () use (
                $workspaceId,
                $locationId,
                $menuId,
                $areaLabels,
                $tableNames,
                $seatCountPerTable,
                $isTokenTaken,
            ): BulkQrCreationResult {
                $now = now();

                $areas = [];

                foreach ($areaLabels as $label) {
                    $areaId = (int) DB::table('dining_areas')->insertGetId([
                        'workspace_id' => $workspaceId,
                        'location_id' => $locationId,
                        'label' => $label,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $areas[] = new DiningAreaRecord($areaId, $workspaceId, $locationId, $label);
                }

                $areaCount = count($areas);
                $tables = [];
                $usedTokens = [];

                foreach ($tableNames as $index => $name) {
                    $area = $areas[$index % $areaCount];

                    $tableId = (int) DB::table('dining_tables')->insertGetId([
                        'workspace_id' => $workspaceId,
                        'location_id' => $locationId,
                        'area_id' => $area->id,
                        'name' => $name,
                        'seat_count' => $seatCountPerTable,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $tables[] = new DiningTableRecord($tableId, $workspaceId, $locationId, $area->id, $name, $seatCountPerTable);
                }

                $qrCodes = [];

                foreach ($tables as $table) {
                    $token = QrToken::generateAvoiding(
                        fn (string $candidate): bool => isset($usedTokens[$candidate]) || $isTokenTaken($candidate)
                    )->value();
                    $usedTokens[$token] = true;

                    $qrCodeId = (int) DB::table('qr_codes')->insertGetId([
                        'workspace_id' => $workspaceId,
                        'location_id' => $locationId,
                        'dining_table_id' => $table->id,
                        'token' => $token,
                        'state' => QrCodeState::Active->value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $destinationId = (int) DB::table('qr_destinations')->insertGetId([
                        'qr_code_id' => $qrCodeId,
                        'destination_type' => self::DESTINATION_TYPE_PUBLISHED_MENU,
                        'menu_id' => $menuId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('qr_code_current_destinations')->insert([
                        'qr_code_id' => $qrCodeId,
                        'qr_destination_id' => $destinationId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $qrCodes[] = new QrCodeRecord(
                        $qrCodeId,
                        $workspaceId,
                        $locationId,
                        $menuId,
                        $token,
                        self::DESTINATION_TYPE_PUBLISHED_MENU,
                        QrCodeState::Active->value,
                    );
                }

                return new BulkQrCreationResult($areas, $tables, $qrCodes);
            });
        } catch (Throwable $e) {
            if ($e instanceof BulkQrCreationFailedException) {
                throw $e;
            }

            throw BulkQrCreationFailedException::fromPrevious($e);
        }
    }
}
