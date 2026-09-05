<?php

declare(strict_types=1);

namespace App\Infrastructure\QrDestination\Persistence;

use App\Application\QrDestination\Dto\QrCodeRecord;
use App\Application\QrDestination\Exception\QrCodePersistenceFailedException;
use App\Application\QrDestination\Port\QrCodeRepositoryPort;
use App\Domain\QrDestination\QrCodeState;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

final class EloquentQrCodeRepository implements QrCodeRepositoryPort
{
    private const DESTINATION_TYPE_PUBLISHED_MENU = 'published_menu';

    public function create(int $workspaceId, int $locationId, int $menuId, string $token): QrCodeRecord
    {
        try {
            return DB::transaction(function () use ($workspaceId, $locationId, $menuId, $token): QrCodeRecord {
                $now = now();

                $qrCodeId = (int) DB::table('qr_codes')->insertGetId([
                    'workspace_id' => $workspaceId,
                    'location_id' => $locationId,
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

                return new QrCodeRecord(
                    $qrCodeId,
                    $workspaceId,
                    $locationId,
                    $menuId,
                    $token,
                    self::DESTINATION_TYPE_PUBLISHED_MENU,
                    QrCodeState::Active->value,
                );
            });
        } catch (QueryException $e) {
            throw QrCodePersistenceFailedException::fromPrevious($e);
        } catch (Throwable $e) {
            if ($e instanceof QrCodePersistenceFailedException) {
                throw $e;
            }

            throw QrCodePersistenceFailedException::fromPrevious($e);
        }
    }

    public function listForLocation(int $workspaceId, int $locationId): array
    {
        return $this->baseQuery()
            ->where('qr_codes.workspace_id', $workspaceId)
            ->where('qr_codes.location_id', $locationId)
            ->orderBy('qr_codes.id')
            ->get()
            ->map(fn (object $row): QrCodeRecord => $this->hydrate($row))
            ->all();
    }

    public function findById(int $qrCodeId): ?QrCodeRecord
    {
        $row = $this->baseQuery()->where('qr_codes.id', $qrCodeId)->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function disable(int $qrCodeId): QrCodeRecord
    {
        try {
            DB::table('qr_codes')->where('id', $qrCodeId)->update([
                'state' => QrCodeState::Disabled->value,
                'updated_at' => now(),
            ]);
        } catch (QueryException $e) {
            throw QrCodePersistenceFailedException::fromPrevious($e);
        }

        $record = $this->findById($qrCodeId);

        if ($record === null) {
            throw QrCodePersistenceFailedException::fromPrevious(new \RuntimeException('QR code missing after disable.'));
        }

        return $record;
    }

    public function enable(int $qrCodeId): QrCodeRecord
    {
        try {
            DB::table('qr_codes')->where('id', $qrCodeId)->update([
                'state' => QrCodeState::Active->value,
                'updated_at' => now(),
            ]);
        } catch (QueryException $e) {
            throw QrCodePersistenceFailedException::fromPrevious($e);
        }

        $record = $this->findById($qrCodeId);

        if ($record === null) {
            throw QrCodePersistenceFailedException::fromPrevious(new \RuntimeException('QR code missing after enable.'));
        }

        return $record;
    }

    public function retarget(int $qrCodeId, int $menuId, int $locationId): QrCodeRecord
    {
        try {
            DB::transaction(function () use ($qrCodeId, $menuId, $locationId): void {
                $now = now();

                // YENİ bir hedef satırı açılır, eskisi durur: "bu kod ne
                // zaman nereye bakıyordu" sorusu geçmişten cevaplanabilmeli
                // (`docs/81`).
                $destinationId = (int) DB::table('qr_destinations')->insertGetId([
                    'qr_code_id' => $qrCodeId,
                    'destination_type' => self::DESTINATION_TYPE_PUBLISHED_MENU,
                    'menu_id' => $menuId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // İşaretçi GÜNCELLENİR, ikinci bir satır açılmaz: "şu an
                // hangi hedef geçerli" sorusunun tek bir cevabı olmalı.
                DB::table('qr_code_current_destinations')
                    ->where('qr_code_id', $qrCodeId)
                    ->update([
                        'qr_destination_id' => $destinationId,
                        'updated_at' => $now,
                    ]);

                // Kodun şubesi menünün şubesine taşınır: aksi hâlde ölçüm,
                // kodun artık göstermediği şubeye yazılırdı.
                DB::table('qr_codes')->where('id', $qrCodeId)->update([
                    'location_id' => $locationId,
                    'updated_at' => $now,
                ]);
            });
        } catch (QueryException $e) {
            throw QrCodePersistenceFailedException::fromPrevious($e);
        }

        $record = $this->findById($qrCodeId);

        if ($record === null) {
            throw QrCodePersistenceFailedException::fromPrevious(new \RuntimeException('QR code missing after retarget.'));
        }

        return $record;
    }

    public function tokenExists(string $token): bool
    {
        return DB::table('qr_codes')->where('token', $token)->exists();
    }

    public function findActiveByToken(string $token): ?QrCodeRecord
    {
        $row = $this->baseQuery()
            ->where('qr_codes.token', $token)
            ->where('qr_codes.state', QrCodeState::Active->value)
            ->first();

        return $row === null ? null : $this->hydrate($row);
    }

    private function baseQuery(): Builder
    {
        /*
            Masa ve alan LEFT JOIN'dir (FF-109): her kod bir masaya ait
            değildir — giriş kodu ya da tek kodlu bir kafe için
            `dining_table_id` boştur. INNER join, o kodları listeden
            düşürürdü; sahip kendi kodunun kaybolduğunu görürdü.
        */
        return DB::table('qr_codes')
            ->join('qr_code_current_destinations', 'qr_code_current_destinations.qr_code_id', '=', 'qr_codes.id')
            ->join('qr_destinations', 'qr_destinations.id', '=', 'qr_code_current_destinations.qr_destination_id')
            ->leftJoin('dining_tables', 'dining_tables.id', '=', 'qr_codes.dining_table_id')
            ->leftJoin('dining_areas', 'dining_areas.id', '=', 'dining_tables.area_id')
            ->select(
                'qr_codes.id as id',
                'qr_codes.workspace_id as workspace_id',
                'qr_codes.location_id as location_id',
                'qr_codes.token as token',
                'qr_codes.state as state',
                'qr_destinations.destination_type as destination_type',
                'qr_destinations.menu_id as menu_id',
                'dining_tables.name as table_name',
                'dining_areas.label as area_label',
                'dining_areas.id as area_id',
                // Masa KİMLİĞİ `qr_codes` üzerinden okunur, birleştirilen
                // masa satırından değil: kod bir masaya bağlıysa bu değer
                // zaten oradadır ve birleştirmenin başarısına bağlı değildir.
                'qr_codes.dining_table_id as dining_table_id',
            );
    }

    private function hydrate(object $row): QrCodeRecord
    {
        return new QrCodeRecord(
            (int) $row->id,
            (int) $row->workspace_id,
            (int) $row->location_id,
            (int) $row->menu_id,
            (string) $row->token,
            (string) $row->destination_type,
            (string) $row->state,
            $row->table_name === null ? null : (string) $row->table_name,
            $row->area_label === null ? null : (string) $row->area_label,
            $row->area_id === null ? null : (int) $row->area_id,
            $row->dining_table_id === null ? null : (int) $row->dining_table_id,
        );
    }
}
