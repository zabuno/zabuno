<?php

declare(strict_types=1);

namespace App\Domain\Security;

use InvalidArgumentException;

/**
 * Medya tatbikatının kanıt kaydı.
 *
 * Bir menü yalnız satırlardan ibaret değil: fotoğraflar `storage/app`
 * altında, veritabanının dışında yaşar ve bugüne kadar hiçbir tatbikat
 * onlara dokunmamıştı. Bu kayıt, medya kökünün bir tar arşivine alınıp
 * izole bir dizine geri açıldığını ve kopyanın aslıyla dosya sayısı,
 * toplam bayt ve dosya başına SHA-256 üzerinden eşleştiğini tutar
 * (docs/124). Veritabanı kaydıyla aynı disiplin: geçmiş bir koşu kendiyle
 * çelişemez, bilinmeyen bir koşu sıfır çıkış kodu taşıyamaz, bütünlük
 * özeti iddia metni dâhil her kanonik alanı kapsar.
 */
final class MediaBackupRestoreEvidenceRecord
{
    private const KEY = 'media_backup_restore';

    private const SCOPE = 'local_media_root_tar_isolated_restore_drill';

    private const RUNNER = 'tar_sha256_manifest';

    private const CLAIM = 'This evidence reflects one tar archive of the local media root and one extraction into an isolated directory on the same host; file count, total bytes and a per-file SHA-256 manifest were compared, then the archive and the restored copy were deleted. It is not an off-host backup, not an RPO/RTO proof, and does not test restoring media into the running application.';

    private const STATUSES = ['passed', 'failed', 'unknown'];

    private function __construct(
        private readonly ?int $id,
        private readonly string $key,
        private readonly string $status,
        private readonly string $scope,
        private readonly string $runner,
        private readonly string $ranAt,
        private readonly int $durationMs,
        private readonly int $exitCode,
        private readonly string $gitSha,
        private readonly bool $gitDirty,
        private readonly string $sourceSnapshotSha256,
        private readonly string $suiteManifestSha256,
        private readonly string $archiveSha256,
        private readonly int $archiveBytes,
        private readonly string $sourceManifestSha256,
        private readonly string $restoredManifestSha256,
        private readonly int $sourceFileCount,
        private readonly int $restoredFileCount,
        private readonly int $sourceBytes,
        private readonly int $restoredBytes,
        private readonly string $outputSha256,
        private readonly string $integritySha256,
        private readonly string $claim,
    ) {}

    public static function fromRun(
        string $status,
        int $durationMs,
        int $exitCode,
        string $gitSha,
        bool $gitDirty,
        string $sourceSnapshotSha256,
        string $suiteManifestSha256,
        string $archiveSha256,
        int $archiveBytes,
        string $sourceManifestSha256,
        string $restoredManifestSha256,
        int $sourceFileCount,
        int $restoredFileCount,
        int $sourceBytes,
        int $restoredBytes,
        string $outputSha256,
        string $ranAt,
    ): self {
        if (! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('A media backup/restore run status must be one of: '.implode(', ', self::STATUSES).'.');
        }

        if ($status === 'passed' && $exitCode !== 0) {
            throw new InvalidArgumentException('A passed media backup/restore run must have a zero exit code.');
        }

        if ($status === 'passed' && $sourceFileCount !== $restoredFileCount) {
            throw new InvalidArgumentException('A passed media backup/restore run must have matching source and restored file counts.');
        }

        if ($status === 'passed' && $sourceBytes !== $restoredBytes) {
            throw new InvalidArgumentException('A passed media backup/restore run must have matching source and restored byte totals.');
        }

        if ($status === 'passed' && ! hash_equals($sourceManifestSha256, $restoredManifestSha256)) {
            throw new InvalidArgumentException('A passed media backup/restore run must have matching source and restored manifests.');
        }

        if ($status === 'unknown' && $exitCode === 0) {
            throw new InvalidArgumentException('An unknown media backup/restore run cannot carry a zero exit code.');
        }

        $integritySha256 = self::computeIntegritySha256(
            key: self::KEY,
            status: $status,
            scope: self::SCOPE,
            runner: self::RUNNER,
            ranAt: $ranAt,
            durationMs: $durationMs,
            exitCode: $exitCode,
            gitSha: $gitSha,
            gitDirty: $gitDirty,
            sourceSnapshotSha256: $sourceSnapshotSha256,
            suiteManifestSha256: $suiteManifestSha256,
            archiveSha256: $archiveSha256,
            archiveBytes: $archiveBytes,
            sourceManifestSha256: $sourceManifestSha256,
            restoredManifestSha256: $restoredManifestSha256,
            sourceFileCount: $sourceFileCount,
            restoredFileCount: $restoredFileCount,
            sourceBytes: $sourceBytes,
            restoredBytes: $restoredBytes,
            outputSha256: $outputSha256,
            claim: self::CLAIM,
        );

        return new self(
            id: null,
            key: self::KEY,
            status: $status,
            scope: self::SCOPE,
            runner: self::RUNNER,
            ranAt: $ranAt,
            durationMs: $durationMs,
            exitCode: $exitCode,
            gitSha: $gitSha,
            gitDirty: $gitDirty,
            sourceSnapshotSha256: $sourceSnapshotSha256,
            suiteManifestSha256: $suiteManifestSha256,
            archiveSha256: $archiveSha256,
            archiveBytes: $archiveBytes,
            sourceManifestSha256: $sourceManifestSha256,
            restoredManifestSha256: $restoredManifestSha256,
            sourceFileCount: $sourceFileCount,
            restoredFileCount: $restoredFileCount,
            sourceBytes: $sourceBytes,
            restoredBytes: $restoredBytes,
            outputSha256: $outputSha256,
            integritySha256: $integritySha256,
            claim: self::CLAIM,
        );
    }

    public static function reconstitute(
        int $id,
        string $key,
        string $status,
        string $scope,
        string $runner,
        string $ranAt,
        int $durationMs,
        int $exitCode,
        string $gitSha,
        bool $gitDirty,
        string $sourceSnapshotSha256,
        string $suiteManifestSha256,
        string $archiveSha256,
        int $archiveBytes,
        string $sourceManifestSha256,
        string $restoredManifestSha256,
        int $sourceFileCount,
        int $restoredFileCount,
        int $sourceBytes,
        int $restoredBytes,
        string $outputSha256,
        string $integritySha256,
        string $claim,
    ): self {
        return new self(
            id: $id,
            key: $key,
            status: $status,
            scope: $scope,
            runner: $runner,
            ranAt: $ranAt,
            durationMs: $durationMs,
            exitCode: $exitCode,
            gitSha: $gitSha,
            gitDirty: $gitDirty,
            sourceSnapshotSha256: $sourceSnapshotSha256,
            suiteManifestSha256: $suiteManifestSha256,
            archiveSha256: $archiveSha256,
            archiveBytes: $archiveBytes,
            sourceManifestSha256: $sourceManifestSha256,
            restoredManifestSha256: $restoredManifestSha256,
            sourceFileCount: $sourceFileCount,
            restoredFileCount: $restoredFileCount,
            sourceBytes: $sourceBytes,
            restoredBytes: $restoredBytes,
            outputSha256: $outputSha256,
            integritySha256: $integritySha256,
            claim: $claim,
        );
    }

    /**
     * Bkz. BackupRestoreEvidenceRecord::canonicalTimestamp — aynı sebep:
     * özet, veritabanı sürücüsünün zaman damgasını nasıl geri verdiğine
     * bağlı olamaz.
     */
    private static function canonicalTimestamp(string $value): string
    {
        try {
            $moment = new \DateTimeImmutable($value);
        } catch (\Exception) {
            return $value;
        }

        return $moment->setTimezone(new \DateTimeZone('UTC'))->format(\DateTimeInterface::ATOM);
    }

    private static function computeIntegritySha256(
        string $key,
        string $status,
        string $scope,
        string $runner,
        string $ranAt,
        int $durationMs,
        int $exitCode,
        string $gitSha,
        bool $gitDirty,
        string $sourceSnapshotSha256,
        string $suiteManifestSha256,
        string $archiveSha256,
        int $archiveBytes,
        string $sourceManifestSha256,
        string $restoredManifestSha256,
        int $sourceFileCount,
        int $restoredFileCount,
        int $sourceBytes,
        int $restoredBytes,
        string $outputSha256,
        string $claim,
    ): string {
        $canonical = implode('|', [
            $key,
            $scope,
            $runner,
            $status,
            self::canonicalTimestamp($ranAt),
            (string) $durationMs,
            (string) $exitCode,
            $gitSha,
            $gitDirty ? '1' : '0',
            $sourceSnapshotSha256,
            $suiteManifestSha256,
            $archiveSha256,
            (string) $archiveBytes,
            $sourceManifestSha256,
            $restoredManifestSha256,
            (string) $sourceFileCount,
            (string) $restoredFileCount,
            (string) $sourceBytes,
            (string) $restoredBytes,
            $outputSha256,
            $claim,
        ]);

        return hash('sha256', $canonical);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function scope(): string
    {
        return $this->scope;
    }

    public function runner(): string
    {
        return $this->runner;
    }

    public function ranAt(): string
    {
        return $this->ranAt;
    }

    public function durationMs(): int
    {
        return $this->durationMs;
    }

    public function exitCode(): int
    {
        return $this->exitCode;
    }

    public function gitSha(): string
    {
        return $this->gitSha;
    }

    public function gitDirty(): bool
    {
        return $this->gitDirty;
    }

    public function sourceSnapshotSha256(): string
    {
        return $this->sourceSnapshotSha256;
    }

    public function suiteManifestSha256(): string
    {
        return $this->suiteManifestSha256;
    }

    public function archiveSha256(): string
    {
        return $this->archiveSha256;
    }

    public function archiveBytes(): int
    {
        return $this->archiveBytes;
    }

    public function sourceManifestSha256(): string
    {
        return $this->sourceManifestSha256;
    }

    public function restoredManifestSha256(): string
    {
        return $this->restoredManifestSha256;
    }

    public function sourceFileCount(): int
    {
        return $this->sourceFileCount;
    }

    public function restoredFileCount(): int
    {
        return $this->restoredFileCount;
    }

    public function sourceBytes(): int
    {
        return $this->sourceBytes;
    }

    public function restoredBytes(): int
    {
        return $this->restoredBytes;
    }

    public function outputSha256(): string
    {
        return $this->outputSha256;
    }

    public function integritySha256(): string
    {
        return $this->integritySha256;
    }

    public function claim(): string
    {
        return $this->claim;
    }

    public function verifiesIntegrity(): bool
    {
        $recomputed = self::computeIntegritySha256(
            key: $this->key,
            status: $this->status,
            scope: $this->scope,
            runner: $this->runner,
            ranAt: $this->ranAt,
            durationMs: $this->durationMs,
            exitCode: $this->exitCode,
            gitSha: $this->gitSha,
            gitDirty: $this->gitDirty,
            sourceSnapshotSha256: $this->sourceSnapshotSha256,
            suiteManifestSha256: $this->suiteManifestSha256,
            archiveSha256: $this->archiveSha256,
            archiveBytes: $this->archiveBytes,
            sourceManifestSha256: $this->sourceManifestSha256,
            restoredManifestSha256: $this->restoredManifestSha256,
            sourceFileCount: $this->sourceFileCount,
            restoredFileCount: $this->restoredFileCount,
            sourceBytes: $this->sourceBytes,
            restoredBytes: $this->restoredBytes,
            outputSha256: $this->outputSha256,
            claim: $this->claim,
        );

        return hash_equals($recomputed, $this->integritySha256);
    }

    /**
     * @return array<string, int|string|bool|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'status' => $this->status,
            'scope' => $this->scope,
            'runner' => $this->runner,
            'ran_at' => $this->ranAt,
            'duration_ms' => $this->durationMs,
            'exit_code' => $this->exitCode,
            'git_sha' => $this->gitSha,
            'git_dirty' => $this->gitDirty,
            'source_snapshot_sha256' => $this->sourceSnapshotSha256,
            'suite_manifest_sha256' => $this->suiteManifestSha256,
            'archive_sha256' => $this->archiveSha256,
            'archive_bytes' => $this->archiveBytes,
            'source_manifest_sha256' => $this->sourceManifestSha256,
            'restored_manifest_sha256' => $this->restoredManifestSha256,
            'source_file_count' => $this->sourceFileCount,
            'restored_file_count' => $this->restoredFileCount,
            'source_bytes' => $this->sourceBytes,
            'restored_bytes' => $this->restoredBytes,
            'output_sha256' => $this->outputSha256,
            'integrity_sha256' => $this->integritySha256,
            'claim' => $this->claim,
        ];
    }
}
