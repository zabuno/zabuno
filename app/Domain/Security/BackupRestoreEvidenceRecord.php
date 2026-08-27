<?php

declare(strict_types=1);

namespace App\Domain\Security;

use InvalidArgumentException;

final class BackupRestoreEvidenceRecord
{
    private const KEY = 'backup_restore';

    private const SCOPE = 'local_sqlite_online_backup_restore_drill';

    private const RUNNER = 'sqlite3_online_backup';

    private const CLAIM = 'This evidence reflects one local SQLite online-backup and isolated file-copy restore drill against a frozen table manifest. It is not an RPO/RTO proof, not a production DR drill, and does not test cross-host or point-in-time recovery.';

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
        private readonly string $backupSha256,
        private readonly string $restoredDbSha256,
        private readonly int $sourceRowCount,
        private readonly int $restoredRowCount,
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
        string $backupSha256,
        string $restoredDbSha256,
        int $sourceRowCount,
        int $restoredRowCount,
        string $outputSha256,
        string $ranAt,
    ): self {
        if ($status === 'passed' && $exitCode !== 0) {
            throw new InvalidArgumentException('A passed backup/restore run must have a zero exit code.');
        }

        if ($status === 'passed' && $sourceRowCount !== $restoredRowCount) {
            throw new InvalidArgumentException('A passed backup/restore run must have matching source and restored row counts.');
        }

        if ($status === 'passed' && ! hash_equals($backupSha256, $restoredDbSha256)) {
            throw new InvalidArgumentException('A passed backup/restore run must have matching backup and restored content hashes.');
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
            backupSha256: $backupSha256,
            restoredDbSha256: $restoredDbSha256,
            sourceRowCount: $sourceRowCount,
            restoredRowCount: $restoredRowCount,
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
            backupSha256: $backupSha256,
            restoredDbSha256: $restoredDbSha256,
            sourceRowCount: $sourceRowCount,
            restoredRowCount: $restoredRowCount,
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
        string $backupSha256,
        string $restoredDbSha256,
        int $sourceRowCount,
        int $restoredRowCount,
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
            backupSha256: $backupSha256,
            restoredDbSha256: $restoredDbSha256,
            sourceRowCount: $sourceRowCount,
            restoredRowCount: $restoredRowCount,
            outputSha256: $outputSha256,
            integritySha256: $integritySha256,
            claim: $claim,
        );
    }

    /**
     * Zaman damgasını depolama motorundan bağımsız tek bir biçime indirger.
     *
     * Bütünlük özeti bu dizeyi hash'liyor. Ham hâliyle bırakıldığında özet,
     * veritabanı sürücüsünün metni nasıl geri verdiğine bağımlı hâle gelir:
     * SQLite yazılan dizeyi aynen döndürür (`2026-08-27T08:00:00+00:00`),
     * PostgreSQL kendi gösterimine çevirir (`2026-08-27 08:00:00+00`). Aynı
     * kayıt, aynı veri, farklı özet — ve kayıt kurcalanmış görünür.
     *
     * Kanıt kayıtlarının varlık sebebi güvenilirliği kanıtlamaktı; özetin
     * sürücüye bağımlı olması tam da o iddiayı çürütüyordu. Kanonik biçim
     * UTC + ISO-8601'dir ve motor değişse de değişmez.
     */
    private static function canonicalTimestamp(string $value): string
    {
        try {
            $moment = new \DateTimeImmutable($value);
        } catch (\Exception) {
            // Ayrıştırılamayan değer sessizce düzeltilmez: özet yanlış
            // olacağına, değer olduğu gibi kalsın ve doğrulama düşsün.
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
        string $backupSha256,
        string $restoredDbSha256,
        int $sourceRowCount,
        int $restoredRowCount,
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
            $backupSha256,
            $restoredDbSha256,
            (string) $sourceRowCount,
            (string) $restoredRowCount,
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

    public function backupSha256(): string
    {
        return $this->backupSha256;
    }

    public function restoredDbSha256(): string
    {
        return $this->restoredDbSha256;
    }

    public function sourceRowCount(): int
    {
        return $this->sourceRowCount;
    }

    public function restoredRowCount(): int
    {
        return $this->restoredRowCount;
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
            backupSha256: $this->backupSha256,
            restoredDbSha256: $this->restoredDbSha256,
            sourceRowCount: $this->sourceRowCount,
            restoredRowCount: $this->restoredRowCount,
            outputSha256: $this->outputSha256,
            claim: $this->claim,
        );

        return hash_equals($recomputed, $this->integritySha256);
    }
}
