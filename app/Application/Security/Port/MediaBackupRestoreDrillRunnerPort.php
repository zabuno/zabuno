<?php

declare(strict_types=1);

namespace App\Application\Security\Port;

interface MediaBackupRestoreDrillRunnerPort
{
    /**
     * Medya kökünü arşivler, izole bir dizine geri açar, dosya sayısı /
     * toplam bayt / dosya başına SHA-256 manifestini karşılaştırır.
     * `measured` = false: tatbikat hiç denenemedi (tar yok, kök yok).
     *
     * @return array{passed: bool, exit_code: int, duration_ms: int, output: string, measured: bool, archive_sha256: string, archive_bytes: int, source_manifest_sha256: string, restored_manifest_sha256: string, source_file_count: int, restored_file_count: int, source_bytes: int, restored_bytes: int}
     */
    public function run(): array;
}
