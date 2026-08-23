<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Security;

use PHPUnit\Framework\TestCase;

/**
 * S1-WP07 RED — unit contract for the backup/restore evidence domain
 * record: the frozen four-table manifest, the immutable field set on a
 * recorded evidence row, and the deterministic integrity digest that
 * must be verified before serialization. Nothing under
 * App\Domain\Security\BackupRestore* exists yet, so every test below
 * fails RED on class resolution, not on assertion logic.
 *
 * Requirement IDs: SEC-BR-MANIFEST-01, SEC-BR-FIELDS-01,
 * SEC-BR-INTEGRITY-01, SEC-BR-CLAIM-01.
 */
final class BackupRestoreEvidenceRecordTest extends TestCase
{
    /**
     * Frozen contract: the drill operates against exactly these four
     * tables, in this order. No other table is in scope.
     */
    public function test_table_manifest_is_frozen_to_exactly_four_named_tables(): void
    {
        $manifestClass = 'App\\Domain\\Security\\BackupRestoreTableManifest';

        $this->assertTrue(
            class_exists($manifestClass),
            'App\\Domain\\Security\\BackupRestoreTableManifest must exist to freeze the four-table drill scope.'
        );

        /** @var list<string> $tables */
        $tables = $manifestClass::tables();

        $this->assertSame([
            'users',
            'workspaces',
            'workspace_memberships',
            'menus',
        ], $tables);
    }

    public function test_manifest_exposes_no_way_to_add_or_override_a_table(): void
    {
        $manifestClass = 'App\\Domain\\Security\\BackupRestoreTableManifest';
        $this->assertTrue(class_exists($manifestClass));

        $this->assertFalse(
            method_exists($manifestClass, 'withTable'),
            'The manifest must not expose a mutation hook for adding drill tables.'
        );
        $this->assertFalse(
            method_exists($manifestClass, 'add'),
            'The manifest must not expose a mutation hook for adding drill tables.'
        );
    }

    /**
     * A recorded evidence row must carry every immutable field the
     * frozen contract requires, with the documented value shapes.
     */
    public function test_evidence_record_exposes_required_immutable_fields_for_a_passed_run(): void
    {
        $recordClass = 'App\\Domain\\Security\\BackupRestoreEvidenceRecord';
        $this->assertTrue(class_exists($recordClass), 'App\\Domain\\Security\\BackupRestoreEvidenceRecord must exist.');

        $record = $recordClass::fromRun(
            status: 'passed',
            durationMs: 2345,
            exitCode: 0,
            gitSha: str_repeat('a', 40),
            gitDirty: false,
            sourceSnapshotSha256: str_repeat('b', 64),
            suiteManifestSha256: str_repeat('c', 64),
            backupSha256: str_repeat('d', 64),
            restoredDbSha256: str_repeat('d', 64),
            sourceRowCount: 12,
            restoredRowCount: 12,
            outputSha256: str_repeat('e', 64),
            ranAt: '2026-08-23T10:00:00+00:00',
        );

        $this->assertSame('backup_restore', $record->key());
        $this->assertSame('passed', $record->status());
        $this->assertSame('local_sqlite_online_backup_restore_drill', $record->scope());
        $this->assertSame('sqlite3_online_backup', $record->runner());
        $this->assertSame('2026-08-23T10:00:00+00:00', $record->ranAt());
        $this->assertSame(2345, $record->durationMs());
        $this->assertGreaterThanOrEqual(0, $record->durationMs());
        $this->assertSame(0, $record->exitCode());
        $this->assertSame(str_repeat('a', 40), $record->gitSha());
        $this->assertFalse($record->gitDirty());
        $this->assertSame(str_repeat('b', 64), $record->sourceSnapshotSha256());
        $this->assertSame(str_repeat('c', 64), $record->suiteManifestSha256());
        $this->assertSame(str_repeat('d', 64), $record->backupSha256());
        $this->assertSame(str_repeat('d', 64), $record->restoredDbSha256());
        $this->assertSame(12, $record->sourceRowCount());
        $this->assertSame(12, $record->restoredRowCount());
        $this->assertSame(str_repeat('e', 64), $record->outputSha256());
        $this->assertNotSame('', $record->integritySha256());
        $this->assertSame(64, strlen($record->integritySha256()));

        $this->assertStringContainsString('local SQLite online-backup', $record->claim());
        $this->assertStringContainsString('isolated file-copy restore', $record->claim());
        $this->assertMatchesRegularExpression('/not.*(an\s+RPO\/RTO\s+proof|a\s+production\s+DR\s+drill)/i', $record->claim());
        $this->assertMatchesRegularExpression('/does\s+not\s+test\s+cross-host\s+or\s+point-in-time\s+recovery/i', $record->claim());
        $this->assertSame(
            'This evidence reflects one local SQLite online-backup and isolated file-copy restore drill against a frozen table manifest. It is not an RPO/RTO proof, not a production DR drill, and does not test cross-host or point-in-time recovery.',
            $record->claim(),
        );
    }

    public function test_evidence_record_honestly_represents_a_failed_run_with_nonzero_exit(): void
    {
        $recordClass = 'App\\Domain\\Security\\BackupRestoreEvidenceRecord';
        $this->assertTrue(class_exists($recordClass));

        $record = $recordClass::fromRun(
            status: 'failed',
            durationMs: 500,
            exitCode: 1,
            gitSha: str_repeat('f', 40),
            gitDirty: true,
            sourceSnapshotSha256: str_repeat('1', 64),
            suiteManifestSha256: str_repeat('2', 64),
            backupSha256: str_repeat('3', 64),
            restoredDbSha256: str_repeat('4', 64),
            sourceRowCount: 12,
            restoredRowCount: 11,
            outputSha256: str_repeat('5', 64),
            ranAt: '2026-08-23T11:00:00+00:00',
        );

        $this->assertSame('failed', $record->status());
        $this->assertNotSame(0, $record->exitCode());
        $this->assertTrue($record->gitDirty());
        $this->assertNotSame($record->sourceRowCount(), $record->restoredRowCount());
    }

    /**
     * The integrity digest is computed deterministically over the
     * canonical evidence fields (SHA-256), so two records built from the
     * exact same field values produce the exact same digest, and any
     * single differing field changes it. Integrity covers every
     * canonical field, including the row counts and both content
     * hashes.
     */
    public function test_integrity_digest_is_deterministic_over_all_canonical_fields(): void
    {
        $recordClass = 'App\\Domain\\Security\\BackupRestoreEvidenceRecord';
        $this->assertTrue(class_exists($recordClass));

        $args = [
            'status' => 'passed',
            'durationMs' => 42,
            'exitCode' => 0,
            'gitSha' => str_repeat('9', 40),
            'gitDirty' => false,
            'sourceSnapshotSha256' => str_repeat('9', 64),
            'suiteManifestSha256' => str_repeat('9', 64),
            'backupSha256' => str_repeat('9', 64),
            'restoredDbSha256' => str_repeat('9', 64),
            'sourceRowCount' => 7,
            'restoredRowCount' => 7,
            'outputSha256' => str_repeat('9', 64),
            'ranAt' => '2026-08-23T12:00:00+00:00',
        ];

        $recordA = $recordClass::fromRun(...$args);
        $recordB = $recordClass::fromRun(...$args);

        $this->assertSame($recordA->integritySha256(), $recordB->integritySha256());

        $tamperedStatus = $recordClass::fromRun(...array_merge($args, ['status' => 'failed']));
        $this->assertNotSame($recordA->integritySha256(), $tamperedStatus->integritySha256());

        // A record with a differing restored row count is only semantically
        // valid as a failed run, so the comparison run itself is failed —
        // not a passed run with a row-count contradiction.
        $tamperedRowCount = $recordClass::fromRun(...array_merge(
            $args,
            ['status' => 'failed', 'exitCode' => 1, 'restoredRowCount' => 6],
        ));
        $this->assertNotSame($recordA->integritySha256(), $tamperedRowCount->integritySha256());

        // Likewise, a differing backup hash (vs. the restored hash) is only
        // semantically valid as a failed run.
        $tamperedBackupHash = $recordClass::fromRun(...array_merge(
            $args,
            ['status' => 'failed', 'exitCode' => 1, 'backupSha256' => str_repeat('8', 64)],
        ));
        $this->assertNotSame($recordA->integritySha256(), $tamperedBackupHash->integritySha256());
    }

    /**
     * A passed run must agree with itself: zero exit code, matching
     * source/restored row counts, and matching backup/restored hashes.
     * The domain object must not allow constructing a passed run whose
     * row counts or content hashes disagree with each other.
     */
    public function test_passed_status_requires_matching_row_counts(): void
    {
        $recordClass = 'App\\Domain\\Security\\BackupRestoreEvidenceRecord';
        $this->assertTrue(class_exists($recordClass));

        $this->expectException(\InvalidArgumentException::class);

        $recordClass::fromRun(
            status: 'passed',
            durationMs: 10,
            exitCode: 0,
            gitSha: str_repeat('7', 40),
            gitDirty: false,
            sourceSnapshotSha256: str_repeat('7', 64),
            suiteManifestSha256: str_repeat('7', 64),
            backupSha256: str_repeat('7', 64),
            restoredDbSha256: str_repeat('7', 64),
            sourceRowCount: 3,
            restoredRowCount: 2,
            outputSha256: str_repeat('7', 64),
            ranAt: '2026-08-23T13:00:00+00:00',
        );
    }

    public function test_passed_status_requires_matching_backup_and_restored_hashes(): void
    {
        $recordClass = 'App\\Domain\\Security\\BackupRestoreEvidenceRecord';
        $this->assertTrue(class_exists($recordClass));

        $this->expectException(\InvalidArgumentException::class);

        $recordClass::fromRun(
            status: 'passed',
            durationMs: 10,
            exitCode: 0,
            gitSha: str_repeat('7', 40),
            gitDirty: false,
            sourceSnapshotSha256: str_repeat('7', 64),
            suiteManifestSha256: str_repeat('7', 64),
            backupSha256: str_repeat('7', 64),
            restoredDbSha256: str_repeat('8', 64),
            sourceRowCount: 3,
            restoredRowCount: 3,
            outputSha256: str_repeat('7', 64),
            ranAt: '2026-08-23T13:00:00+00:00',
        );
    }

    /**
     * Integrity must cover every canonical field, including the
     * fixed key/scope/runner identity and the exact claim text — not
     * only the run-specific fields. A reconstituted row whose key,
     * scope, runner, or claim has been tampered with (relative to the
     * value that produced its stored digest) must fail verification.
     */
    public function test_verification_fails_closed_when_key_scope_runner_or_claim_is_tampered(): void
    {
        $recordClass = 'App\\Domain\\Security\\BackupRestoreEvidenceRecord';
        $this->assertTrue(class_exists($recordClass));

        $record = $recordClass::fromRun(
            status: 'passed',
            durationMs: 10,
            exitCode: 0,
            gitSha: str_repeat('6', 40),
            gitDirty: false,
            sourceSnapshotSha256: str_repeat('6', 64),
            suiteManifestSha256: str_repeat('6', 64),
            backupSha256: str_repeat('6', 64),
            restoredDbSha256: str_repeat('6', 64),
            sourceRowCount: 4,
            restoredRowCount: 4,
            outputSha256: str_repeat('6', 64),
            ranAt: '2026-08-23T14:00:00+00:00',
        );

        $this->assertTrue($record->verifiesIntegrity());

        $baseArgs = [
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
            'backupSha256' => $record->backupSha256(),
            'restoredDbSha256' => $record->restoredDbSha256(),
            'sourceRowCount' => $record->sourceRowCount(),
            'restoredRowCount' => $record->restoredRowCount(),
            'outputSha256' => $record->outputSha256(),
            'integritySha256' => $record->integritySha256(),
            'claim' => $record->claim(),
        ];

        $tamperedKey = $recordClass::reconstitute(...array_merge($baseArgs, ['key' => 'not_backup_restore']));
        $this->assertFalse($tamperedKey->verifiesIntegrity(), 'A tampered key must fail integrity verification.');

        $tamperedScope = $recordClass::reconstitute(...array_merge($baseArgs, ['scope' => 'tampered_scope']));
        $this->assertFalse($tamperedScope->verifiesIntegrity(), 'A tampered scope must fail integrity verification.');

        $tamperedRunner = $recordClass::reconstitute(...array_merge($baseArgs, ['runner' => 'tampered_runner']));
        $this->assertFalse($tamperedRunner->verifiesIntegrity(), 'A tampered runner must fail integrity verification.');

        $tamperedClaim = $recordClass::reconstitute(...array_merge($baseArgs, ['claim' => 'This is not the frozen claim text.']));
        $this->assertFalse($tamperedClaim->verifiesIntegrity(), 'A tampered claim must fail integrity verification.');
    }

    /**
     * Verification must fail closed on tamper: a record whose stored
     * integrity digest no longer matches its recomputed digest over the
     * canonical fields must never verify as passed/valid.
     */
    public function test_verification_fails_closed_when_integrity_digest_does_not_match(): void
    {
        $recordClass = 'App\\Domain\\Security\\BackupRestoreEvidenceRecord';
        $this->assertTrue(class_exists($recordClass));

        $record = $recordClass::fromRun(
            status: 'passed',
            durationMs: 10,
            exitCode: 0,
            gitSha: str_repeat('7', 40),
            gitDirty: false,
            sourceSnapshotSha256: str_repeat('7', 64),
            suiteManifestSha256: str_repeat('7', 64),
            backupSha256: str_repeat('7', 64),
            restoredDbSha256: str_repeat('7', 64),
            sourceRowCount: 3,
            restoredRowCount: 3,
            outputSha256: str_repeat('7', 64),
            ranAt: '2026-08-23T13:00:00+00:00',
        );

        $this->assertTrue($record->verifiesIntegrity());

        $tampered = $recordClass::reconstitute(
            id: 1,
            key: $record->key(),
            status: $record->status(),
            scope: $record->scope(),
            runner: $record->runner(),
            ranAt: $record->ranAt(),
            durationMs: $record->durationMs(),
            exitCode: $record->exitCode(),
            gitSha: $record->gitSha(),
            gitDirty: $record->gitDirty(),
            sourceSnapshotSha256: $record->sourceSnapshotSha256(),
            suiteManifestSha256: $record->suiteManifestSha256(),
            backupSha256: $record->backupSha256(),
            restoredDbSha256: $record->restoredDbSha256(),
            sourceRowCount: $record->sourceRowCount(),
            restoredRowCount: $record->restoredRowCount(),
            outputSha256: $record->outputSha256(),
            integritySha256: str_repeat('0', 64),
            claim: $record->claim(),
        );

        $this->assertFalse($tampered->verifiesIntegrity());
    }

    /**
     * A passed status must always carry a zero exit code and matching
     * source/restored row counts; the domain object does not allow
     * constructing a passed run that disagrees with itself.
     */
    public function test_passed_status_requires_zero_exit_code_and_matching_row_counts(): void
    {
        $recordClass = 'App\\Domain\\Security\\BackupRestoreEvidenceRecord';
        $this->assertTrue(class_exists($recordClass));

        $this->expectException(\InvalidArgumentException::class);

        $recordClass::fromRun(
            status: 'passed',
            durationMs: 10,
            exitCode: 1,
            gitSha: str_repeat('7', 40),
            gitDirty: false,
            sourceSnapshotSha256: str_repeat('7', 64),
            suiteManifestSha256: str_repeat('7', 64),
            backupSha256: str_repeat('7', 64),
            restoredDbSha256: str_repeat('7', 64),
            sourceRowCount: 3,
            restoredRowCount: 3,
            outputSha256: str_repeat('7', 64),
            ranAt: '2026-08-23T13:00:00+00:00',
        );
    }
}
