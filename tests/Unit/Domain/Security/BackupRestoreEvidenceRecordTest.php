<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Security;

use App\Domain\Security\BackupRestoreDriver;
use PHPUnit\Framework\TestCase;

/**
 * S1-WP07 RED — unit contract for the backup/restore evidence domain
 * record: the frozen four-table manifest, the immutable field set on a
 * recorded evidence row, and the deterministic integrity digest that
 * must be verified before serialization.
 *
 * FF-199 (docs/124) extends the record: it now names the DRIVER that
 * ran (`sqlite` or `pgsql`), carries the measured backup size and the
 * two phase durations, and admits a third status — `unknown` — for a
 * drill that could not be attempted at all (missing pg_dump, missing
 * source). "Unknown" is never "passed"; the record refuses an unknown
 * run that claims a zero exit code.
 *
 * Requirement IDs: SEC-BR-MANIFEST-01, SEC-BR-FIELDS-01,
 * SEC-BR-INTEGRITY-01, SEC-BR-CLAIM-01, SEC-BR-DRIVER-01,
 * SEC-BR-UNKNOWN-01.
 */
final class BackupRestoreEvidenceRecordTest extends TestCase
{
    private const RECORD = 'App\\Domain\\Security\\BackupRestoreEvidenceRecord';

    /**
     * @return array<string, mixed>
     */
    private function passedArgs(): array
    {
        return [
            'status' => 'passed',
            'durationMs' => 2345,
            'exitCode' => 0,
            'gitSha' => str_repeat('a', 40),
            'gitDirty' => false,
            'sourceSnapshotSha256' => str_repeat('b', 64),
            'suiteManifestSha256' => str_repeat('c', 64),
            'backupSha256' => str_repeat('d', 64),
            'restoredDbSha256' => str_repeat('d', 64),
            'sourceRowCount' => 12,
            'restoredRowCount' => 12,
            'outputSha256' => str_repeat('e', 64),
            'ranAt' => '2026-08-23T10:00:00+00:00',
            'driver' => BackupRestoreDriver::Sqlite,
            'backupBytes' => 20480,
            'backupMs' => 120,
            'restoreMs' => 80,
        ];
    }

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
     * The snapshot digest must cover every runner that can produce this
     * evidence, not only the SQLite one — otherwise a change to the
     * PostgreSQL runner would leave the recorded source hash untouched.
     */
    public function test_manifest_source_paths_cover_every_database_runner(): void
    {
        $manifestClass = 'App\\Domain\\Security\\BackupRestoreTableManifest';

        $paths = $manifestClass::sourcePaths();

        $this->assertContains('app/Domain/Security/BackupRestoreTableManifest.php', $paths);
        $this->assertContains('app/Infrastructure/Security/Execution/SqliteBackupRestoreDrillRunner.php', $paths);
        $this->assertContains('app/Infrastructure/Security/Execution/PostgresBackupRestoreDrillRunner.php', $paths);

        $mediaPaths = $manifestClass::mediaSourcePaths();
        $this->assertContains('app/Infrastructure/Security/Execution/TarMediaBackupRestoreDrillRunner.php', $mediaPaths);
        $this->assertContains('app/Domain/Security/MediaBackupRestoreEvidenceRecord.php', $mediaPaths);
    }

    /**
     * A recorded evidence row must carry every immutable field the
     * frozen contract requires, with the documented value shapes.
     */
    public function test_evidence_record_exposes_required_immutable_fields_for_a_passed_run(): void
    {
        $recordClass = self::RECORD;
        $this->assertTrue(class_exists($recordClass), 'App\\Domain\\Security\\BackupRestoreEvidenceRecord must exist.');

        $record = $recordClass::fromRun(...$this->passedArgs());

        $this->assertSame('backup_restore', $record->key());
        $this->assertSame('passed', $record->status());
        $this->assertSame('local_sqlite_online_backup_restore_drill', $record->scope());
        $this->assertSame('sqlite3_online_backup', $record->runner());
        $this->assertSame('sqlite', $record->driver());
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
        $this->assertSame(20480, $record->backupBytes());
        $this->assertSame(120, $record->backupMs());
        $this->assertSame(80, $record->restoreMs());
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

    /**
     * The PostgreSQL driver gives the record its own identity: a scope
     * and runner name that say what actually ran (pg_dump custom
     * archive, pg_restore into a throwaway database) and a claim that
     * refuses to be read as a production DR drill.
     */
    public function test_pgsql_driver_record_carries_its_own_scope_runner_and_claim(): void
    {
        $recordClass = self::RECORD;

        $record = $recordClass::fromRun(...array_merge($this->passedArgs(), [
            'driver' => BackupRestoreDriver::Pgsql,
        ]));

        $this->assertSame('pgsql', $record->driver());
        $this->assertSame('postgres_pg_dump_isolated_database_restore_drill', $record->scope());
        $this->assertSame('pg_dump_custom_pg_restore', $record->runner());
        $this->assertStringContainsString('pg_dump', $record->claim());
        $this->assertStringContainsString('pg_restore', $record->claim());
        $this->assertStringContainsString('throwaway database', $record->claim());
        $this->assertMatchesRegularExpression('/not.*(an\s+RPO\/RTO\s+proof|a\s+production\s+DR\s+drill)/i', $record->claim());
        $this->assertMatchesRegularExpression('/does\s+not\s+test\s+cross-host\s+or\s+point-in-time\s+recovery/i', $record->claim());
        $this->assertNotSame(BackupRestoreDriver::Sqlite->claim(), $record->claim());
    }

    public function test_evidence_record_honestly_represents_a_failed_run_with_nonzero_exit(): void
    {
        $recordClass = self::RECORD;
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
            driver: BackupRestoreDriver::Sqlite,
            backupBytes: 0,
            backupMs: 0,
            restoreMs: 0,
        );

        $this->assertSame('failed', $record->status());
        $this->assertNotSame(0, $record->exitCode());
        $this->assertTrue($record->gitDirty());
        $this->assertNotSame($record->sourceRowCount(), $record->restoredRowCount());
    }

    /**
     * "Unknown" is the honest status for a drill that never ran: no
     * pg_dump on the host, no readable source. It is recorded, it is
     * never upgraded to passed, and it cannot pretend to a zero exit.
     */
    public function test_unknown_status_is_recorded_honestly_with_a_nonzero_exit(): void
    {
        $recordClass = self::RECORD;

        $record = $recordClass::fromRun(...array_merge($this->passedArgs(), [
            'status' => 'unknown',
            'exitCode' => 127,
            'driver' => BackupRestoreDriver::Pgsql,
            'backupSha256' => str_repeat('0', 64),
            'restoredDbSha256' => str_repeat('0', 64),
            'sourceRowCount' => 0,
            'restoredRowCount' => 0,
            'backupBytes' => 0,
            'backupMs' => 0,
            'restoreMs' => 0,
        ]));

        $this->assertSame('unknown', $record->status());
        $this->assertSame(127, $record->exitCode());
        $this->assertTrue($record->verifiesIntegrity());
    }

    public function test_unknown_status_requires_a_nonzero_exit_code(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (self::RECORD)::fromRun(...array_merge($this->passedArgs(), [
            'status' => 'unknown',
            'exitCode' => 0,
        ]));
    }

    public function test_an_unrecognized_status_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (self::RECORD)::fromRun(...array_merge($this->passedArgs(), [
            'status' => 'skipped',
            'exitCode' => 1,
        ]));
    }

    /**
     * The integrity digest is computed deterministically over the
     * canonical evidence fields (SHA-256), so two records built from the
     * exact same field values produce the exact same digest, and any
     * single differing field changes it. Integrity covers every
     * canonical field, including the row counts, both content hashes,
     * the driver and the measured sizes.
     */
    public function test_integrity_digest_is_deterministic_over_all_canonical_fields(): void
    {
        $recordClass = self::RECORD;
        $this->assertTrue(class_exists($recordClass));

        $args = array_merge($this->passedArgs(), [
            'durationMs' => 42,
            'gitSha' => str_repeat('9', 40),
            'sourceSnapshotSha256' => str_repeat('9', 64),
            'suiteManifestSha256' => str_repeat('9', 64),
            'backupSha256' => str_repeat('9', 64),
            'restoredDbSha256' => str_repeat('9', 64),
            'sourceRowCount' => 7,
            'restoredRowCount' => 7,
            'outputSha256' => str_repeat('9', 64),
            'ranAt' => '2026-08-23T12:00:00+00:00',
        ]);

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

        $otherDriver = $recordClass::fromRun(...array_merge($args, ['driver' => BackupRestoreDriver::Pgsql]));
        $this->assertNotSame($recordA->integritySha256(), $otherDriver->integritySha256(), 'The driver must be covered by the digest.');

        $otherBytes = $recordClass::fromRun(...array_merge($args, ['backupBytes' => 1]));
        $this->assertNotSame($recordA->integritySha256(), $otherBytes->integritySha256(), 'The backup size must be covered by the digest.');

        $otherBackupMs = $recordClass::fromRun(...array_merge($args, ['backupMs' => 1]));
        $this->assertNotSame($recordA->integritySha256(), $otherBackupMs->integritySha256(), 'The backup duration must be covered by the digest.');

        $otherRestoreMs = $recordClass::fromRun(...array_merge($args, ['restoreMs' => 1]));
        $this->assertNotSame($recordA->integritySha256(), $otherRestoreMs->integritySha256(), 'The restore duration must be covered by the digest.');
    }

    /**
     * A passed run must agree with itself: zero exit code, matching
     * source/restored row counts, and matching backup/restored hashes.
     * The domain object must not allow constructing a passed run whose
     * row counts or content hashes disagree with each other.
     */
    public function test_passed_status_requires_matching_row_counts(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (self::RECORD)::fromRun(...array_merge($this->passedArgs(), [
            'sourceRowCount' => 3,
            'restoredRowCount' => 2,
        ]));
    }

    public function test_passed_status_requires_matching_backup_and_restored_hashes(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (self::RECORD)::fromRun(...array_merge($this->passedArgs(), [
            'backupSha256' => str_repeat('7', 64),
            'restoredDbSha256' => str_repeat('8', 64),
        ]));
    }

    /**
     * Integrity must cover every canonical field, including the
     * fixed key/scope/runner identity, the driver and the exact claim
     * text — not only the run-specific fields. A reconstituted row whose
     * key, scope, runner, driver or claim has been tampered with
     * (relative to the value that produced its stored digest) must fail
     * verification.
     */
    public function test_verification_fails_closed_when_key_scope_runner_driver_or_claim_is_tampered(): void
    {
        $recordClass = self::RECORD;

        $record = $recordClass::fromRun(...$this->passedArgs());

        $this->assertTrue($record->verifiesIntegrity());

        $baseArgs = $this->reconstituteArgs($record);

        $tamperedKey = $recordClass::reconstitute(...array_merge($baseArgs, ['key' => 'not_backup_restore']));
        $this->assertFalse($tamperedKey->verifiesIntegrity(), 'A tampered key must fail integrity verification.');

        $tamperedScope = $recordClass::reconstitute(...array_merge($baseArgs, ['scope' => 'tampered_scope']));
        $this->assertFalse($tamperedScope->verifiesIntegrity(), 'A tampered scope must fail integrity verification.');

        $tamperedRunner = $recordClass::reconstitute(...array_merge($baseArgs, ['runner' => 'tampered_runner']));
        $this->assertFalse($tamperedRunner->verifiesIntegrity(), 'A tampered runner must fail integrity verification.');

        $tamperedDriver = $recordClass::reconstitute(...array_merge($baseArgs, ['driver' => 'pgsql']));
        $this->assertFalse($tamperedDriver->verifiesIntegrity(), 'A tampered driver must fail integrity verification.');

        $tamperedBytes = $recordClass::reconstitute(...array_merge($baseArgs, ['backupBytes' => 1]));
        $this->assertFalse($tamperedBytes->verifiesIntegrity(), 'A tampered backup size must fail integrity verification.');

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
        $recordClass = self::RECORD;

        $record = $recordClass::fromRun(...$this->passedArgs());

        $this->assertTrue($record->verifiesIntegrity());

        $tampered = $recordClass::reconstitute(...array_merge(
            $this->reconstituteArgs($record),
            ['integritySha256' => str_repeat('0', 64)],
        ));

        $this->assertFalse($tampered->verifiesIntegrity());
    }

    /**
     * A passed status must always carry a zero exit code and matching
     * source/restored row counts; the domain object does not allow
     * constructing a passed run that disagrees with itself.
     */
    public function test_passed_status_requires_zero_exit_code_and_matching_row_counts(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (self::RECORD)::fromRun(...array_merge($this->passedArgs(), [
            'exitCode' => 1,
        ]));
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
            'driver' => $record->driver(),
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
            'backupBytes' => $record->backupBytes(),
            'backupMs' => $record->backupMs(),
            'restoreMs' => $record->restoreMs(),
            'outputSha256' => $record->outputSha256(),
            'integritySha256' => $record->integritySha256(),
            'claim' => $record->claim(),
        ];
    }
}
