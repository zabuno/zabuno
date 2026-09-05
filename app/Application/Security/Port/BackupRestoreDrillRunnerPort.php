<?php

declare(strict_types=1);

namespace App\Application\Security\Port;

use App\Domain\Security\BackupRestoreDriver;

interface BackupRestoreDrillRunnerPort
{
    /**
     * Hangi motorun tatbikatı — kanıt kaydı bu adı taşır.
     */
    public function driver(): BackupRestoreDriver;

    /**
     * `measured` = false, tatbikatın HİÇ DENENEMEDİĞİ anlamına gelir
     * (araç yok, kaynak okunamıyor); kayıt o zaman `unknown` olur.
     * `backup_sha256` yedeklenen içeriğin, `restored_db_sha256` geri
     * yüklenen içeriğin özetidir; geçmiş bir tatbikatta ikisi eşittir.
     *
     * @param  list<string>  $tables
     * @return array{passed: bool, exit_code: int, duration_ms: int, output: string, backup_sha256: string, restored_db_sha256: string, source_row_count: int, restored_row_count: int, restored_integrity_ok: bool, measured: bool, backup_bytes: int, backup_ms: int, restore_ms: int}
     */
    public function run(array $tables): array;
}
