<?php

declare(strict_types=1);

namespace App\Http\Controllers\QrDestination;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Entitlement\Exception\EntitlementDeniedException;
use App\Application\Entitlement\UseCase\RequireEntitlement;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Application\Publication\Port\PublicationRepositoryPort;
use App\Application\QrDestination\Exception\BulkQrCreationFailedException;
use App\Application\QrDestination\Port\BulkQrCreationPort;
use App\Application\QrDestination\Port\QrCodeRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Domain\Entitlement\Entitlement;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StoreBulkQrCodesController extends Controller
{
    private const AREA_SECTION_COUNT_MIN = 1;

    private const AREA_SECTION_COUNT_MAX = 50;

    private const TABLE_COUNT_MIN = 1;

    private const TABLE_COUNT_MAX = 500;

    private const NAMING_PREFIX_MAX_LENGTH = 10;

    private const NAMING_NUMBER_MIN = 0;

    private const NAMING_NUMBER_MAX = 9999;

    private const SEAT_COUNT_MIN = 1;

    private const SEAT_COUNT_MAX = 20;

    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly MenuCatalogApiContextPort $context,
        private readonly PublicationRepositoryPort $publications,
        private readonly QrCodeRepositoryPort $qrCodes,
        private readonly BulkQrCreationPort $bulkCreation,
        private readonly RequireEntitlement $requireEntitlement,
    ) {}

    public function __invoke(Request $request, int $workspace, int $location): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::QrView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::QrCreate, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($this->context->locationWorkspaceId($location) !== $workspace) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        // CORE-04: bu yetenek plana bağlıdır (owner kararı, 2026-08-26).
        // 402 kullanılır, 403 değil: kullanıcı yetkisiz DEĞİL, planı bu
        // yeteneği içermiyor. Çıkış yolu farklıdır — biri erişim talebi,
        // diğeri plan yükseltmesidir; aynı kodu vermek kullanıcıyı yanlış
        // yola sokar.
        try {
            $this->requireEntitlement->handle($workspace, Entitlement::QrBulkGeneration);
        } catch (EntitlementDeniedException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'entitlement' => $e->entitlement->value,
            ], 402);
        }

        $menuId = (int) $request->input('menuId');

        if ($menuId <= 0 || $this->context->menuIdForLocation($workspace, $location) !== $menuId) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $plan = $this->validatePlan($request);

        if ($plan === null) {
            return response()->json(['message' => 'Invalid bulk table creation request.'], 422);
        }

        if ($this->publications->current($workspace, $menuId) === null) {
            return response()->json(['message' => 'No current publication for this menu.'], 422);
        }

        [$areaSectionCount, $tableNumbers, $seatCountPerTable, $namingPrefix] = $plan;

        $areaLabels = [];

        for ($i = 1; $i <= $areaSectionCount; $i++) {
            $areaLabels[] = "Area {$i}";
        }

        $tableNames = array_map(
            static fn (int $number): string => $namingPrefix.$number,
            $tableNumbers,
        );

        try {
            $result = $this->bulkCreation->create(
                $workspace,
                $location,
                $menuId,
                $areaLabels,
                $tableNames,
                $seatCountPerTable,
                fn (string $candidate): bool => $this->qrCodes->tokenExists($candidate),
            );
        } catch (BulkQrCreationFailedException) {
            return response()->json(['message' => 'Bulk QR creation failed.'], 500);
        }

        return response()->json([
            'areas' => array_map(static fn ($area): array => [
                'id' => $area->id,
                'workspaceId' => $area->workspaceId,
                'locationId' => $area->locationId,
                'label' => $area->label,
            ], $result->areas),
            'tables' => array_map(static fn ($table): array => [
                'id' => $table->id,
                'workspaceId' => $table->workspaceId,
                'locationId' => $table->locationId,
                'areaId' => $table->areaId,
                'name' => $table->name,
                'seatCount' => $table->seatCount,
            ], $result->tables),
            'qrCodes' => array_map(static function ($qrCode, $table): array {
                return [
                    'id' => $qrCode->id,
                    'tableId' => $table->id,
                    'workspaceId' => $qrCode->workspaceId,
                    'locationId' => $qrCode->locationId,
                    'menuId' => $qrCode->menuId,
                    'token' => $qrCode->token,
                    'resolverUrl' => url("/q/{$qrCode->token}"),
                    'destinationType' => $qrCode->destinationType,
                    'state' => $qrCode->state,
                ];
            }, $result->qrCodes, $result->tables),
        ], 201);
    }

    /**
     * @return array{0: int, 1: list<int>, 2: int, 3: string}|null [areaSectionCount, tableNumbers, seatCountPerTable, namingPrefix]
     */
    private function validatePlan(Request $request): ?array
    {
        $areaSectionCount = $request->input('areaSectionCount');
        $tableCount = $request->input('tableCount');
        $seatCountPerTable = $request->input('seatCountPerTable');
        $namingPrefixRaw = $request->input('namingPrefix');
        $namingSequenceStartRaw = $request->input('namingSequenceStart');
        $namingRangeRaw = $request->input('namingRange');

        if (! is_int($areaSectionCount) || $areaSectionCount < self::AREA_SECTION_COUNT_MIN || $areaSectionCount > self::AREA_SECTION_COUNT_MAX) {
            return null;
        }

        if (! is_int($tableCount) || $tableCount < self::TABLE_COUNT_MIN || $tableCount > self::TABLE_COUNT_MAX) {
            return null;
        }

        if (! is_int($seatCountPerTable) || $seatCountPerTable < self::SEAT_COUNT_MIN || $seatCountPerTable > self::SEAT_COUNT_MAX) {
            return null;
        }

        $namingPrefix = 'T';

        if ($namingPrefixRaw !== null) {
            if (! is_string($namingPrefixRaw)) {
                return null;
            }

            $trimmed = trim($namingPrefixRaw);

            if ($trimmed !== '') {
                if (mb_strlen($trimmed) > self::NAMING_PREFIX_MAX_LENGTH) {
                    return null;
                }

                $namingPrefix = $trimmed;
            }
        }

        $namingSequenceStart = null;

        if ($namingSequenceStartRaw !== null) {
            if (! is_int($namingSequenceStartRaw) || $namingSequenceStartRaw < self::NAMING_NUMBER_MIN || $namingSequenceStartRaw > self::NAMING_NUMBER_MAX) {
                return null;
            }

            $namingSequenceStart = $namingSequenceStartRaw;
        }

        $rangeStart = null;
        $rangeEnd = null;

        if ($namingRangeRaw !== null) {
            if (! is_string($namingRangeRaw) || preg_match('/^(\d{1,4})-(\d{1,4})$/', $namingRangeRaw, $matches) !== 1) {
                return null;
            }

            $rangeStart = (int) $matches[1];
            $rangeEnd = (int) $matches[2];

            if ($rangeStart > $rangeEnd) {
                return null;
            }

            if (($rangeEnd - $rangeStart + 1) !== $tableCount) {
                return null;
            }

            if ($namingSequenceStart !== null && $namingSequenceStart !== $rangeStart) {
                return null;
            }
        }

        if ($rangeStart !== null) {
            $tableNumbers = range($rangeStart, $rangeEnd);
        } else {
            $start = $namingSequenceStart ?? 1;
            $derivedEnd = $start + $tableCount - 1;

            if ($derivedEnd > self::NAMING_NUMBER_MAX) {
                return null;
            }

            $tableNumbers = range($start, $derivedEnd);
        }

        return [$areaSectionCount, $tableNumbers, $seatCountPerTable, $namingPrefix];
    }
}
