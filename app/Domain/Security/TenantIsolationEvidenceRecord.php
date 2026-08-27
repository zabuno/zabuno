<?php

declare(strict_types=1);

namespace App\Domain\Security;

final class TenantIsolationEvidenceRecord
{
    private const KEY = 'tenant_isolation';

    private const SCOPE = 'automated_local_feature_tests';

    private const RUNNER = 'phpunit';

    private const CLAIM = 'This evidence reflects the result of running the frozen set of selected automated local feature tests for tenant isolation. It is not an ASVS audit, not a pentest, and not a production proof.';

    private function __construct(
        private readonly ?int $id,
        private readonly string $status,
        private readonly string $ranAt,
        private readonly int $durationMs,
        private readonly int $exitCode,
        private readonly string $gitSha,
        private readonly bool $gitDirty,
        private readonly string $sourceSnapshotSha256,
        private readonly string $suiteManifestSha256,
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
        string $outputSha256,
        string $ranAt,
    ): self {
        $integritySha256 = self::computeIntegritySha256(
            status: $status,
            ranAt: $ranAt,
            durationMs: $durationMs,
            exitCode: $exitCode,
            gitSha: $gitSha,
            gitDirty: $gitDirty,
            sourceSnapshotSha256: $sourceSnapshotSha256,
            suiteManifestSha256: $suiteManifestSha256,
            outputSha256: $outputSha256,
        );

        return new self(
            id: null,
            status: $status,
            ranAt: $ranAt,
            durationMs: $durationMs,
            exitCode: $exitCode,
            gitSha: $gitSha,
            gitDirty: $gitDirty,
            sourceSnapshotSha256: $sourceSnapshotSha256,
            suiteManifestSha256: $suiteManifestSha256,
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
        string $outputSha256,
        string $integritySha256,
        string $claim,
    ): self {
        return new self(
            id: $id,
            status: $status,
            ranAt: $ranAt,
            durationMs: $durationMs,
            exitCode: $exitCode,
            gitSha: $gitSha,
            gitDirty: $gitDirty,
            sourceSnapshotSha256: $sourceSnapshotSha256,
            suiteManifestSha256: $suiteManifestSha256,
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
        string $status,
        string $ranAt,
        int $durationMs,
        int $exitCode,
        string $gitSha,
        bool $gitDirty,
        string $sourceSnapshotSha256,
        string $suiteManifestSha256,
        string $outputSha256,
    ): string {
        $canonical = implode('|', [
            self::KEY,
            self::SCOPE,
            self::RUNNER,
            $status,
            self::canonicalTimestamp($ranAt),
            (string) $durationMs,
            (string) $exitCode,
            $gitSha,
            $gitDirty ? '1' : '0',
            $sourceSnapshotSha256,
            $suiteManifestSha256,
            $outputSha256,
        ]);

        return hash('sha256', $canonical);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function scope(): string
    {
        return self::SCOPE;
    }

    public function runner(): string
    {
        return self::RUNNER;
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
            status: $this->status,
            ranAt: $this->ranAt,
            durationMs: $this->durationMs,
            exitCode: $this->exitCode,
            gitSha: $this->gitSha,
            gitDirty: $this->gitDirty,
            sourceSnapshotSha256: $this->sourceSnapshotSha256,
            suiteManifestSha256: $this->suiteManifestSha256,
            outputSha256: $this->outputSha256,
        );

        return hash_equals($recomputed, $this->integritySha256);
    }
}
