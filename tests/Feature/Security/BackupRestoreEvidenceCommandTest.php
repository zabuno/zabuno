<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Domain\Security\BackupRestoreDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * S1-WP07 — the operator-only Artisan command
 * `security:evidence:backup-restore` runs the database drill through an
 * injected runner port against the frozen four-table manifest and
 * append-only records one evidence row, exiting 0 only on pass.
 *
 * FF-199 (docs/124) widens the command without loosening it: the same
 * invocation now also runs the MEDIA drill (`storage/app` media root,
 * tar round trip) and appends its own row; `--database` or `--media`
 * narrow the run to one of the two; `--json` prints what was appended.
 * The database row names the driver that ran (`sqlite`/`pgsql`), and a
 * drill that could not be attempted at all is recorded as `unknown` —
 * never as passed, never silently skipped.
 *
 * Both runner ports are interfaces bound as Mockery fakes; no fake ever
 * touches a database file, a media root or a shell.
 *
 * Requirement IDs: SEC-BR-CMD-NO-OPTIONS-01, SEC-BR-CMD-APPEND-01,
 * SEC-BR-CMD-FAIL-HONEST-01, SEC-BR-CMD-EXIT-CODE-01,
 * SEC-BR-CMD-FROZEN-TABLES-01, SEC-BR-CMD-MEDIA-01,
 * SEC-BR-CMD-JSON-01, SEC-BR-CMD-DRIVER-01, SEC-BR-CMD-UNKNOWN-01.
 */
final class BackupRestoreEvidenceCommandTest extends TestCase
{
    use RefreshDatabase;

    private const RUNNER_PORT = 'App\\Application\\Security\\Port\\BackupRestoreDrillRunnerPort';

    private const MEDIA_RUNNER_PORT = 'App\\Application\\Security\\Port\\MediaBackupRestoreDrillRunnerPort';

    private const COMMAND = 'security:evidence:backup-restore';

    private const FROZEN_TABLES = ['users', 'workspaces', 'workspace_memberships', 'menus'];

    /**
     * @return array<string, mixed>
     */
    private function runResult(bool $pass, int $durationMs = 888, string $rawOutput = 'backup-restore drill OK'): array
    {
        return [
            'passed' => $pass,
            'exit_code' => $pass ? 0 : 1,
            'duration_ms' => $durationMs,
            'output' => $rawOutput,
            'backup_sha256' => str_repeat('a', 64),
            'restored_db_sha256' => $pass ? str_repeat('a', 64) : str_repeat('b', 64),
            'source_row_count' => 9,
            'restored_row_count' => $pass ? 9 : 8,
            'restored_integrity_ok' => $pass,
            'measured' => true,
            'backup_bytes' => 40960,
            'backup_ms' => 300,
            'restore_ms' => 200,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mediaRunResult(bool $pass, bool $measured = true): array
    {
        return [
            'passed' => $pass,
            'exit_code' => $pass ? 0 : ($measured ? 1 : 127),
            'duration_ms' => 1200,
            'output' => $pass ? 'media drill OK' : 'media drill failed',
            'measured' => $measured,
            'archive_sha256' => str_repeat('c', 64),
            'archive_bytes' => 51200,
            'source_manifest_sha256' => str_repeat('d', 64),
            'restored_manifest_sha256' => $pass ? str_repeat('d', 64) : str_repeat('e', 64),
            'source_file_count' => 14,
            'restored_file_count' => 14,
            'source_bytes' => 48000,
            'restored_bytes' => 48000,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $result
     */
    private function bindRunnerFake(bool $pass, ?array $result = null, BackupRestoreDriver $driver = BackupRestoreDriver::Sqlite): MockInterface
    {
        $fake = Mockery::mock(self::RUNNER_PORT);
        $fake->shouldReceive('driver')->andReturn($driver);
        $fake->shouldReceive('run')
            ->with(self::FROZEN_TABLES)
            ->andReturn($result ?? $this->runResult($pass));

        app()->instance(self::RUNNER_PORT, $fake);

        return $fake;
    }

    /**
     * @param  array<string, mixed>|null  $result
     */
    private function bindMediaRunnerFake(bool $pass, ?array $result = null): MockInterface
    {
        $fake = Mockery::mock(self::MEDIA_RUNNER_PORT);
        $fake->shouldReceive('run')->andReturn($result ?? $this->mediaRunResult($pass));

        app()->instance(self::MEDIA_RUNNER_PORT, $fake);

        return $fake;
    }

    private function bindBothFakes(bool $databasePass = true, bool $mediaPass = true): void
    {
        $this->bindRunnerFake($databasePass);
        $this->bindMediaRunnerFake($mediaPass);
    }

    public function test_command_class_and_runner_ports_exist(): void
    {
        $this->assertTrue(interface_exists(self::RUNNER_PORT), self::RUNNER_PORT.' must exist as an injected port interface.');
        $this->assertTrue(interface_exists(self::MEDIA_RUNNER_PORT), self::MEDIA_RUNNER_PORT.' must exist as an injected port interface.');
    }

    /**
     * No user-supplied status/path/table/file options exist on this
     * command — the outcome is derived entirely from the injected
     * runner results, never from a caller-controlled flag.
     */
    public function test_command_accepts_no_status_path_table_or_file_options(): void
    {
        $this->bindBothFakes();

        $exitCode = $this->artisan(self::COMMAND, ['--status' => 'passed'])->run();
        $this->assertSame(1, $exitCode, 'Passing an unknown --status option must fail the command, not select an outcome.');

        $exitCode = $this->artisan(self::COMMAND, ['--table' => 'users'])->run();
        $this->assertSame(1, $exitCode, 'Passing an unknown --table option must fail the command, not scope the drill.');

        $exitCode = $this->artisan(self::COMMAND, ['--path' => '/tmp/whatever.sqlite'])->run();
        $this->assertSame(1, $exitCode, 'Passing an unknown --path option must fail the command, not select a database file.');

        $exitCode = $this->artisan(self::COMMAND, ['--file' => '/tmp/whatever.sqlite'])->run();
        $this->assertSame(1, $exitCode, 'Passing an unknown --file option must fail the command, not select a database file.');
    }

    public function test_a_passing_run_appends_one_database_row_and_one_media_row_and_exits_zero(): void
    {
        $this->bindBothFakes();

        $this->assertSame(0, DB::table('backup_restore_evidence')->count());
        $this->assertSame(0, DB::table('media_backup_restore_evidence')->count());

        $this->artisan(self::COMMAND)->assertExitCode(0);

        $this->assertSame(1, DB::table('backup_restore_evidence')->count());
        $this->assertSame(1, DB::table('media_backup_restore_evidence')->count());

        $row = DB::table('backup_restore_evidence')->first();
        $this->assertSame('backup_restore', $row->key);
        $this->assertSame('passed', $row->status);
        $this->assertSame('local_sqlite_online_backup_restore_drill', $row->scope);
        $this->assertSame('sqlite3_online_backup', $row->runner);
        $this->assertSame('sqlite', $row->driver);
        $this->assertSame(0, (int) $row->exit_code);
        $this->assertSame(9, (int) $row->source_row_count);
        $this->assertSame(9, (int) $row->restored_row_count);
        $this->assertSame(40960, (int) $row->backup_bytes);
        $this->assertSame(300, (int) $row->backup_ms);
        $this->assertSame(200, (int) $row->restore_ms);

        $media = DB::table('media_backup_restore_evidence')->first();
        $this->assertSame('media_backup_restore', $media->key);
        $this->assertSame('passed', $media->status);
        $this->assertSame('local_media_root_tar_isolated_restore_drill', $media->scope);
        $this->assertSame('tar_sha256_manifest', $media->runner);
        $this->assertSame(14, (int) $media->source_file_count);
        $this->assertSame(14, (int) $media->restored_file_count);
        $this->assertSame(48000, (int) $media->source_bytes);
        $this->assertSame(48000, (int) $media->restored_bytes);
        $this->assertSame(51200, (int) $media->archive_bytes);
        $this->assertSame(1200, (int) $media->duration_ms);
    }

    /**
     * The row says which driver ran. On the deployment target the
     * PostgreSQL runner answers `pgsql`, and the scope/runner identity
     * follows it.
     */
    public function test_the_database_row_carries_the_pgsql_driver_identity_when_that_runner_ran(): void
    {
        $this->bindRunnerFake(pass: true, driver: BackupRestoreDriver::Pgsql);
        $this->bindMediaRunnerFake(pass: true);

        $this->artisan(self::COMMAND, ['--database' => true])->assertExitCode(0);

        $row = DB::table('backup_restore_evidence')->first();
        $this->assertSame('pgsql', $row->driver);
        $this->assertSame('postgres_pg_dump_isolated_database_restore_drill', $row->scope);
        $this->assertSame('pg_dump_custom_pg_restore', $row->runner);
        $this->assertSame('passed', $row->status);
    }

    public function test_database_only_flag_runs_only_the_database_drill(): void
    {
        $this->bindRunnerFake(pass: true);
        $media = $this->bindMediaRunnerFake(pass: true);

        $this->artisan(self::COMMAND, ['--database' => true])->assertExitCode(0);

        $this->assertSame(1, DB::table('backup_restore_evidence')->count());
        $this->assertSame(0, DB::table('media_backup_restore_evidence')->count());
        $media->shouldNotHaveReceived('run');
    }

    public function test_media_only_flag_runs_only_the_media_drill(): void
    {
        $database = $this->bindRunnerFake(pass: true);
        $this->bindMediaRunnerFake(pass: true);

        $this->artisan(self::COMMAND, ['--media' => true])->assertExitCode(0);

        $this->assertSame(0, DB::table('backup_restore_evidence')->count());
        $this->assertSame(1, DB::table('media_backup_restore_evidence')->count());
        $database->shouldNotHaveReceived('run');
    }

    /**
     * A failed run is recorded honestly — status failed, nonzero exit
     * code stored and returned — never silently upgraded to passed.
     */
    public function test_a_failing_database_run_records_failed_status_and_exits_nonzero(): void
    {
        $this->bindBothFakes(databasePass: false);

        $exitCode = $this->artisan(self::COMMAND)->run();

        $this->assertNotSame(0, $exitCode);

        $row = DB::table('backup_restore_evidence')->orderByDesc('id')->first();
        $this->assertNotNull($row);
        $this->assertSame('failed', $row->status);
        $this->assertNotSame(0, (int) $row->exit_code);
    }

    /**
     * The media drill failing is enough to fail the command, even when
     * the database drill passed: a restaurant without its photos has not
     * been restored.
     */
    public function test_a_failing_media_run_fails_the_command_even_when_the_database_passed(): void
    {
        $this->bindBothFakes(databasePass: true, mediaPass: false);

        $exitCode = $this->artisan(self::COMMAND)->run();

        $this->assertNotSame(0, $exitCode);
        $this->assertSame('passed', DB::table('backup_restore_evidence')->first()->status);

        $media = DB::table('media_backup_restore_evidence')->first();
        $this->assertSame('failed', $media->status);
        $this->assertNotSame(0, (int) $media->exit_code);
    }

    /**
     * A drill that could not be attempted (no pg_dump on the host, no
     * readable source) is recorded as `unknown`: not passed, not a
     * verdict about a backup that was never taken — and the command
     * exits nonzero so a scheduler or an operator sees it.
     */
    public function test_a_drill_that_was_not_measured_is_recorded_as_unknown_and_exits_nonzero(): void
    {
        $unknown = array_merge($this->runResult(false), [
            'exit_code' => 127,
            'output' => 'pg_dump not found on PATH; drill result unknown',
            'measured' => false,
            'backup_sha256' => str_repeat('0', 64),
            'restored_db_sha256' => str_repeat('0', 64),
            'source_row_count' => 0,
            'restored_row_count' => 0,
            'backup_bytes' => 0,
            'backup_ms' => 0,
            'restore_ms' => 0,
        ]);
        $this->bindRunnerFake(pass: false, result: $unknown, driver: BackupRestoreDriver::Pgsql);
        $this->bindMediaRunnerFake(pass: false, result: $this->mediaRunResult(false, measured: false));

        $exitCode = $this->artisan(self::COMMAND)->run();

        $this->assertNotSame(0, $exitCode);

        $row = DB::table('backup_restore_evidence')->first();
        $this->assertSame('unknown', $row->status);
        $this->assertSame(127, (int) $row->exit_code);
        $this->assertSame('pgsql', $row->driver);

        $media = DB::table('media_backup_restore_evidence')->first();
        $this->assertSame('unknown', $media->status);
        $this->assertSame(127, (int) $media->exit_code);
    }

    /**
     * A runner that says "not measured" but also "passed" contradicts
     * itself; the contradiction resolves to unknown, never to passed.
     */
    public function test_an_unmeasured_result_can_never_be_recorded_as_passed(): void
    {
        $contradiction = array_merge($this->runResult(true), ['measured' => false, 'exit_code' => 0]);
        $this->bindRunnerFake(pass: true, result: $contradiction);
        $this->bindMediaRunnerFake(pass: true);

        $exitCode = $this->artisan(self::COMMAND, ['--database' => true])->run();

        $this->assertNotSame(0, $exitCode);
        $row = DB::table('backup_restore_evidence')->first();
        $this->assertSame('unknown', $row->status);
        $this->assertNotSame(0, (int) $row->exit_code);
    }

    /**
     * `--json` prints exactly what was appended — one object per drill,
     * `null` for a drill that was not selected — so a scheduler log or
     * an operator terminal carries the evidence without a second query.
     */
    public function test_json_flag_prints_the_appended_evidence_as_json(): void
    {
        $this->bindRunnerFake(pass: true, driver: BackupRestoreDriver::Pgsql);
        $this->bindMediaRunnerFake(pass: true);

        $this->assertSame(0, Artisan::call(self::COMMAND, ['--json' => true]));
        $output = Artisan::output();
        $this->assertStringContainsString('"driver": "pgsql"', $output);
        $this->assertStringContainsString('"key": "media_backup_restore"', $output);

        $this->bindRunnerFake(pass: true);
        $this->bindMediaRunnerFake(pass: true);

        $this->assertSame(0, Artisan::call(self::COMMAND, ['--json' => true, '--database' => true]));
        $output = Artisan::output();
        $this->assertStringContainsString('"media": null', $output);
        $this->assertStringContainsString('"passed": true', $output);
    }

    public function test_json_output_is_valid_json_and_carries_no_raw_output_or_paths(): void
    {
        $this->bindRunnerFake(pass: true);
        $this->bindMediaRunnerFake(pass: true);

        $this->assertSame(0, Artisan::call(self::COMMAND, ['--json' => true]));

        $output = trim(Artisan::output());
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        $this->assertTrue($decoded['passed']);
        $this->assertSame('passed', $decoded['database']['status']);
        $this->assertSame('sqlite', $decoded['database']['driver']);
        $this->assertSame(40960, $decoded['database']['backup_bytes']);
        $this->assertSame('passed', $decoded['media']['status']);
        $this->assertSame(14, $decoded['media']['source_file_count']);
        $this->assertArrayNotHasKey('output', $decoded['database']);
        $this->assertArrayNotHasKey('output', $decoded['media']);
        $this->assertDoesNotMatchRegularExpression('/"[a-z_]*(path|dsn|password|connection)[a-z_]*":/', $output);
    }

    /**
     * Re-running the command inserts a second row rather than updating
     * the first — the evidence log is append-only, with no update/delete
     * path.
     */
    public function test_rerunning_the_command_appends_a_second_row_never_updates_the_first(): void
    {
        $this->bindBothFakes();
        $this->artisan(self::COMMAND)->assertExitCode(0);

        $this->bindBothFakes(databasePass: false, mediaPass: false);
        $this->artisan(self::COMMAND)->run();

        $rows = DB::table('backup_restore_evidence')->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('passed', $rows[0]->status);
        $this->assertSame('failed', $rows[1]->status);

        $mediaRows = DB::table('media_backup_restore_evidence')->orderBy('id')->get();
        $this->assertCount(2, $mediaRows);
        $this->assertSame('passed', $mediaRows[0]->status);
        $this->assertSame('failed', $mediaRows[1]->status);
    }

    /**
     * Exactly one persistence class per evidence log, and neither may
     * expose an update or delete path — the logs are append-only.
     */
    public function test_evidence_repositories_expose_no_update_or_delete_path(): void
    {
        foreach ([
            'App\\Infrastructure\\Security\\Persistence\\BackupRestoreEvidenceRepository',
            'App\\Infrastructure\\Security\\Persistence\\MediaBackupRestoreEvidenceRepository',
        ] as $repositoryClass) {
            $this->assertTrue(class_exists($repositoryClass), $repositoryClass.' must exist as the sole evidence persistence class.');
            $this->assertFalse(method_exists($repositoryClass, 'update'), $repositoryClass.' must not expose an update path.');
            $this->assertFalse(method_exists($repositoryClass, 'delete'), $repositoryClass.' must not expose a delete path.');
        }
    }

    /**
     * The runner is invoked with exactly the frozen four-table manifest —
     * never a caller-influenced table list. Enforced above via
     * shouldReceive('run')->with(self::FROZEN_TABLES); a call with any
     * other argument list makes the mock expectation unmet and fails the
     * test.
     */
    public function test_command_invokes_runner_with_exactly_the_frozen_four_tables(): void
    {
        $fake = $this->bindRunnerFake(pass: true);
        $this->bindMediaRunnerFake(pass: true);

        $this->artisan(self::COMMAND)->run();

        $fake->shouldHaveReceived('run')->once()->with(self::FROZEN_TABLES);
    }

    /**
     * A runner may itself be compromised or buggy and claim
     * passed/exit-0 while its own restored_integrity_ok flag says the
     * restored database's integrity check failed. The application must
     * not trust `passed` alone — it must append a failed evidence row
     * and exit nonzero rather than propagate the runner's contradictory
     * claim.
     */
    public function test_a_runner_claiming_pass_despite_failed_restored_integrity_is_recorded_as_failed(): void
    {
        $this->bindRunnerFake(pass: true, result: array_merge($this->runResult(true), ['restored_integrity_ok' => false]));
        $this->bindMediaRunnerFake(pass: true);

        $exitCode = $this->artisan(self::COMMAND)->run();

        $this->assertNotSame(0, $exitCode, 'The command must not exit successfully when restored_integrity_ok is false, regardless of the runner\'s passed claim.');

        $row = DB::table('backup_restore_evidence')->orderByDesc('id')->first();
        $this->assertNotNull($row);
        $this->assertSame('failed', $row->status, 'The evidence row must be recorded as failed, not upgraded to passed, when restored_integrity_ok is false.');
        $this->assertNotSame(0, (int) $row->exit_code);
    }

    /**
     * A runner may claim passed/exit-0 with matching hashes and row
     * counts while omitting the mandatory restored_integrity_ok key
     * entirely. The application must not default a missing signal to
     * true — it must append a failed evidence row and exit nonzero.
     */
    public function test_a_runner_claiming_pass_with_missing_restored_integrity_signal_is_recorded_as_failed(): void
    {
        $result = $this->runResult(true);
        unset($result['restored_integrity_ok']);
        $this->bindRunnerFake(pass: true, result: $result);
        $this->bindMediaRunnerFake(pass: true);

        $exitCode = $this->artisan(self::COMMAND)->run();

        $this->assertNotSame(0, $exitCode, 'The command must not exit successfully when restored_integrity_ok is missing from the runner result, regardless of the runner\'s passed claim.');

        $row = DB::table('backup_restore_evidence')->orderByDesc('id')->first();
        $this->assertNotNull($row);
        $this->assertSame('failed', $row->status, 'The evidence row must be recorded as failed, not upgraded to passed, when restored_integrity_ok is missing.');
        $this->assertNotSame(0, (int) $row->exit_code);
    }
}
