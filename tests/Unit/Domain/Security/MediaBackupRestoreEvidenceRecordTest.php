<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Security;

use PHPUnit\Framework\TestCase;

/**
 * FF-199 RED (docs/124) — the media backup/restore evidence record.
 *
 * A menu is not only rows: the photos live under `storage/app`, outside
 * the database, and until now no drill ever touched them. This record
 * captures one tar archive of the media root and one extraction into
 * an isolated directory, verified by file count, total bytes and a
 * per-file SHA-256 manifest. Same discipline as the database record:
 * a passed run must agree with itself, an unknown run cannot claim a
 * zero exit, and the integrity digest covers every canonical field
 * including the exact claim text.
 *
 * Requirement IDs: SEC-BR-MEDIA-FIELDS-01, SEC-BR-MEDIA-INTEGRITY-01,
 * SEC-BR-MEDIA-CLAIM-01, SEC-BR-MEDIA-UNKNOWN-01.
 */
final class MediaBackupRestoreEvidenceRecordTest extends TestCase
{
    private const RECORD = 'App\\Domain\\Security\\MediaBackupRestoreEvidenceRecord';

    /**
     * @return array<string, mixed>
     */
    private function passedArgs(): array
    {
        return [
            'status' => 'passed',
            'durationMs' => 910,
            'exitCode' => 0,
            'gitSha' => str_repeat('a', 40),
            'gitDirty' => false,
            'sourceSnapshotSha256' => str_repeat('b', 64),
            'suiteManifestSha256' => str_repeat('c', 64),
            'archiveSha256' => str_repeat('d', 64),
            'archiveBytes' => 30720,
            'sourceManifestSha256' => str_repeat('e', 64),
            'restoredManifestSha256' => str_repeat('e', 64),
            'sourceFileCount' => 14,
            'restoredFileCount' => 14,
            'sourceBytes' => 26112,
            'restoredBytes' => 26112,
            'outputSha256' => str_repeat('f', 64),
            'ranAt' => '2026-09-06T03:40:00+00:00',
        ];
    }

    public function test_record_exposes_required_immutable_fields_for_a_passed_run(): void
    {
        $recordClass = self::RECORD;
        $this->assertTrue(class_exists($recordClass), self::RECORD.' must exist.');

        $record = $recordClass::fromRun(...$this->passedArgs());

        $this->assertSame('media_backup_restore', $record->key());
        $this->assertSame('passed', $record->status());
        $this->assertSame('local_media_root_tar_isolated_restore_drill', $record->scope());
        $this->assertSame('tar_sha256_manifest', $record->runner());
        $this->assertSame('2026-09-06T03:40:00+00:00', $record->ranAt());
        $this->assertSame(910, $record->durationMs());
        $this->assertSame(0, $record->exitCode());
        $this->assertSame(str_repeat('a', 40), $record->gitSha());
        $this->assertFalse($record->gitDirty());
        $this->assertSame(str_repeat('b', 64), $record->sourceSnapshotSha256());
        $this->assertSame(str_repeat('c', 64), $record->suiteManifestSha256());
        $this->assertSame(str_repeat('d', 64), $record->archiveSha256());
        $this->assertSame(30720, $record->archiveBytes());
        $this->assertSame(str_repeat('e', 64), $record->sourceManifestSha256());
        $this->assertSame(str_repeat('e', 64), $record->restoredManifestSha256());
        $this->assertSame(14, $record->sourceFileCount());
        $this->assertSame(14, $record->restoredFileCount());
        $this->assertSame(26112, $record->sourceBytes());
        $this->assertSame(26112, $record->restoredBytes());
        $this->assertSame(str_repeat('f', 64), $record->outputSha256());
        $this->assertSame(64, strlen($record->integritySha256()));

        $this->assertStringContainsString('tar archive', $record->claim());
        $this->assertStringContainsString('isolated directory', $record->claim());
        $this->assertStringContainsString('SHA-256', $record->claim());
        $this->assertMatchesRegularExpression('/not\s+an\s+off-host\s+backup/i', $record->claim());
        $this->assertMatchesRegularExpression('/not\s+an\s+RPO\/RTO\s+proof/i', $record->claim());
    }

    public function test_failed_run_is_recorded_honestly(): void
    {
        $record = (self::RECORD)::fromRun(...array_merge($this->passedArgs(), [
            'status' => 'failed',
            'exitCode' => 1,
            'restoredManifestSha256' => str_repeat('0', 64),
        ]));

        $this->assertSame('failed', $record->status());
        $this->assertSame(1, $record->exitCode());
        $this->assertNotSame($record->sourceManifestSha256(), $record->restoredManifestSha256());
    }

    public function test_unknown_run_is_recorded_with_a_nonzero_exit(): void
    {
        $record = (self::RECORD)::fromRun(...array_merge($this->passedArgs(), [
            'status' => 'unknown',
            'exitCode' => 127,
            'archiveSha256' => str_repeat('0', 64),
            'archiveBytes' => 0,
            'sourceManifestSha256' => str_repeat('0', 64),
            'restoredManifestSha256' => str_repeat('0', 64),
            'sourceFileCount' => 0,
            'restoredFileCount' => 0,
            'sourceBytes' => 0,
            'restoredBytes' => 0,
        ]));

        $this->assertSame('unknown', $record->status());
        $this->assertSame(127, $record->exitCode());
        $this->assertTrue($record->verifiesIntegrity());
    }

    public function test_unknown_status_requires_a_nonzero_exit_code(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (self::RECORD)::fromRun(...array_merge($this->passedArgs(), ['status' => 'unknown']));
    }

    public function test_passed_status_requires_zero_exit_code(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (self::RECORD)::fromRun(...array_merge($this->passedArgs(), ['exitCode' => 1]));
    }

    public function test_passed_status_requires_matching_file_counts(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (self::RECORD)::fromRun(...array_merge($this->passedArgs(), ['restoredFileCount' => 13]));
    }

    public function test_passed_status_requires_matching_byte_totals(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (self::RECORD)::fromRun(...array_merge($this->passedArgs(), ['restoredBytes' => 26111]));
    }

    public function test_passed_status_requires_matching_manifests(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (self::RECORD)::fromRun(...array_merge($this->passedArgs(), ['restoredManifestSha256' => str_repeat('1', 64)]));
    }

    public function test_an_unrecognized_status_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (self::RECORD)::fromRun(...array_merge($this->passedArgs(), ['status' => 'ok']));
    }

    public function test_integrity_digest_is_deterministic_and_covers_every_canonical_field(): void
    {
        $recordClass = self::RECORD;
        $args = $this->passedArgs();

        $a = $recordClass::fromRun(...$args);
        $b = $recordClass::fromRun(...$args);
        $this->assertSame($a->integritySha256(), $b->integritySha256());

        $variants = [
            ['status' => 'failed', 'exitCode' => 1],
            ['durationMs' => 911],
            ['gitSha' => str_repeat('9', 40)],
            ['gitDirty' => true],
            ['sourceSnapshotSha256' => str_repeat('9', 64)],
            ['suiteManifestSha256' => str_repeat('9', 64)],
            ['archiveSha256' => str_repeat('9', 64)],
            ['archiveBytes' => 1],
            ['status' => 'failed', 'exitCode' => 1, 'sourceManifestSha256' => str_repeat('9', 64)],
            ['status' => 'failed', 'exitCode' => 1, 'restoredFileCount' => 1],
            ['status' => 'failed', 'exitCode' => 1, 'restoredBytes' => 1],
            ['outputSha256' => str_repeat('9', 64)],
            ['ranAt' => '2026-09-07T03:40:00+00:00'],
        ];

        foreach ($variants as $variant) {
            $other = $recordClass::fromRun(...array_merge($args, $variant));
            $this->assertNotSame(
                $a->integritySha256(),
                $other->integritySha256(),
                'Field(s) '.implode(',', array_keys($variant)).' must be covered by the digest.'
            );
        }
    }

    public function test_verification_fails_closed_when_identity_or_claim_is_tampered(): void
    {
        $recordClass = self::RECORD;
        $record = $recordClass::fromRun(...$this->passedArgs());
        $this->assertTrue($record->verifiesIntegrity());

        $base = $this->reconstituteArgs($record);

        foreach ([
            ['key' => 'backup_restore'],
            ['scope' => 'tampered'],
            ['runner' => 'tampered'],
            ['claim' => 'Not the frozen claim.'],
            ['status' => 'failed'],
            ['restoredFileCount' => 1],
            ['integritySha256' => str_repeat('0', 64)],
        ] as $tamper) {
            $tampered = $recordClass::reconstitute(...array_merge($base, $tamper));
            $this->assertFalse($tampered->verifiesIntegrity(), 'Tampering '.implode(',', array_keys($tamper)).' must fail verification.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function reconstituteArgs(object $record): array
    {
        return [
            'id' => 1,
            'key' => $record->key(),
            'status' => $record->status(),
            'scope' => $record->scope(),
            'runner' => $record->runner(),
            'ranAt' => $record->ranAt(),
            'durationMs' => $record->durationMs(),
            'exitCode' => $record->exitCode(),
            'gitSha' => $record->gitSha(),
            'gitDirty' => $record->gitDirty(),
            'sourceSnapshotSha256' => $record->sourceSnapshotSha256(),
            'suiteManifestSha256' => $record->suiteManifestSha256(),
            'archiveSha256' => $record->archiveSha256(),
            'archiveBytes' => $record->archiveBytes(),
            'sourceManifestSha256' => $record->sourceManifestSha256(),
            'restoredManifestSha256' => $record->restoredManifestSha256(),
            'sourceFileCount' => $record->sourceFileCount(),
            'restoredFileCount' => $record->restoredFileCount(),
            'sourceBytes' => $record->sourceBytes(),
            'restoredBytes' => $record->restoredBytes(),
            'outputSha256' => $record->outputSha256(),
            'integritySha256' => $record->integritySha256(),
            'claim' => $record->claim(),
        ];
    }
}
