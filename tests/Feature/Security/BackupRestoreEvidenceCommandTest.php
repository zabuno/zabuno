<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * S1-WP07 RED — the operator-only Artisan command
 * `security:evidence:backup-restore` must run a local SQLite
 * online-backup/isolated-file-copy-restore drill through an injected
 * runner port against the frozen four-table manifest, and append-only
 * record a new evidence row, exiting 0 only on pass. Neither the
 * command nor its runner port exists yet, so every test below fails RED
 * on missing class/binding, not on assertion logic.
 *
 * RUNNER_PORT is an interface, not a concrete class. Mockery::mock() can
 * create a mock for an interface that does not exist yet, and container
 * instance() binding is keyed by string, so binding never triggers
 * autoloading of the not-yet-written production port — mirrors
 * TenantIsolationEvidenceCommandTest::bindRunnerFake().
 *
 * Requirement IDs: SEC-BR-CMD-NO-OPTIONS-01, SEC-BR-CMD-APPEND-01,
 * SEC-BR-CMD-FAIL-HONEST-01, SEC-BR-CMD-EXIT-CODE-01,
 * SEC-BR-CMD-FROZEN-TABLES-01.
 */
final class BackupRestoreEvidenceCommandTest extends TestCase
{
    use RefreshDatabase;

    private const RUNNER_PORT = 'App\\Application\\Security\\Port\\BackupRestoreDrillRunnerPort';

    private const COMMAND = 'security:evidence:backup-restore';

    private const FROZEN_TABLES = ['users', 'workspaces', 'workspace_memberships', 'menus'];

    /**
     * @return array{passed: bool, exit_code: int, duration_ms: int, output: string, backup_sha256: string, restored_db_sha256: string, source_row_count: int, restored_row_count: int, restored_integrity_ok: bool}
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
        ];
    }

    /**
     * A runner result that claims passed/exit-0 with matching row counts
     * and hashes, yet explicitly reports restored_integrity_ok = false —
     * as a compromised or buggy runner implementation might. The
     * application must not trust `passed` alone; it must fail closed on
     * this contradiction.
     *
     * @return array{passed: bool, exit_code: int, duration_ms: int, output: string, backup_sha256: string, restored_db_sha256: string, source_row_count: int, restored_row_count: int, restored_integrity_ok: bool}
     */
    private function runResultClaimingPassDespiteFailedIntegrity(int $durationMs = 888, string $rawOutput = 'backup-restore drill OK'): array
    {
        return [
            'passed' => true,
            'exit_code' => 0,
            'duration_ms' => $durationMs,
            'output' => $rawOutput,
            'backup_sha256' => str_repeat('a', 64),
            'restored_db_sha256' => str_repeat('a', 64),
            'source_row_count' => 9,
            'restored_row_count' => 9,
            'restored_integrity_ok' => false,
        ];
    }

    private function bindRunnerFake(bool $pass, int $durationMs = 888, string $rawOutput = 'backup-restore drill OK'): MockInterface
    {
        $fake = Mockery::mock(self::RUNNER_PORT);
        $fake->shouldReceive('run')
            ->with(self::FROZEN_TABLES)
            ->andReturn($this->runResult($pass, $durationMs, $rawOutput));

        app()->instance(self::RUNNER_PORT, $fake);

        return $fake;
    }

    private function bindRunnerFakeClaimingPassDespiteFailedIntegrity(int $durationMs = 888, string $rawOutput = 'backup-restore drill OK'): MockInterface
    {
        $fake = Mockery::mock(self::RUNNER_PORT);
        $fake->shouldReceive('run')
            ->with(self::FROZEN_TABLES)
            ->andReturn($this->runResultClaimingPassDespiteFailedIntegrity($durationMs, $rawOutput));

        app()->instance(self::RUNNER_PORT, $fake);

        return $fake;
    }

    /**
     * A runner result that claims passed/exit-0 with matching row counts
     * and hashes, but omits the restored_integrity_ok key entirely — as a
     * buggy or compromised runner implementation might. The application
     * must not treat a missing signal as an implicit pass; it must fail
     * closed exactly as it does for an explicit restored_integrity_ok =
     * false.
     *
     * @return array{passed: bool, exit_code: int, duration_ms: int, output: string, backup_sha256: string, restored_db_sha256: string, source_row_count: int, restored_row_count: int}
     */
    private function runResultClaimingPassWithMissingIntegritySignal(int $durationMs = 888, string $rawOutput = 'backup-restore drill OK'): array
    {
        return [
            'passed' => true,
            'exit_code' => 0,
            'duration_ms' => $durationMs,
            'output' => $rawOutput,
            'backup_sha256' => str_repeat('a', 64),
            'restored_db_sha256' => str_repeat('a', 64),
            'source_row_count' => 9,
            'restored_row_count' => 9,
        ];
    }

    private function bindRunnerFakeClaimingPassWithMissingIntegritySignal(int $durationMs = 888, string $rawOutput = 'backup-restore drill OK'): MockInterface
    {
        $fake = Mockery::mock(self::RUNNER_PORT);
        $fake->shouldReceive('run')
            ->with(self::FROZEN_TABLES)
            ->andReturn($this->runResultClaimingPassWithMissingIntegritySignal($durationMs, $rawOutput));

        app()->instance(self::RUNNER_PORT, $fake);

        return $fake;
    }

    public function test_command_class_and_runner_port_exist(): void
    {
        $this->assertTrue(interface_exists(self::RUNNER_PORT), self::RUNNER_PORT.' must exist as an injected port interface.');
    }

    /**
     * No user-supplied status/path/table/file options exist on this
     * command — the outcome is derived entirely from the injected
     * runner result, never from a caller-controlled flag.
     */
    public function test_command_accepts_no_status_path_table_or_file_options(): void
    {
        $this->bindRunnerFake(pass: true);

        $exitCode = $this->artisan(self::COMMAND, ['--status' => 'passed'])->run();
        $this->assertSame(1, $exitCode, 'Passing an unknown --status option must fail the command, not select an outcome.');

        $exitCode = $this->artisan(self::COMMAND, ['--table' => 'users'])->run();
        $this->assertSame(1, $exitCode, 'Passing an unknown --table option must fail the command, not scope the drill.');

        $exitCode = $this->artisan(self::COMMAND, ['--path' => '/tmp/whatever.sqlite'])->run();
        $this->assertSame(1, $exitCode, 'Passing an unknown --path option must fail the command, not select a database file.');

        $exitCode = $this->artisan(self::COMMAND, ['--file' => '/tmp/whatever.sqlite'])->run();
        $this->assertSame(1, $exitCode, 'Passing an unknown --file option must fail the command, not select a database file.');
    }

    public function test_a_passing_run_appends_one_evidence_row_and_exits_zero(): void
    {
        $this->bindRunnerFake(pass: true);

        $this->assertSame(0, DB::table('backup_restore_evidence')->count());

        $this->artisan(self::COMMAND)->assertExitCode(0);

        $this->assertSame(1, DB::table('backup_restore_evidence')->count());

        $row = DB::table('backup_restore_evidence')->first();
        $this->assertSame('backup_restore', $row->key);
        $this->assertSame('passed', $row->status);
        $this->assertSame('local_sqlite_online_backup_restore_drill', $row->scope);
        $this->assertSame('sqlite3_online_backup', $row->runner);
        $this->assertSame(0, (int) $row->exit_code);
        $this->assertSame(9, (int) $row->source_row_count);
        $this->assertSame(9, (int) $row->restored_row_count);
    }

    /**
     * A failed run is recorded honestly — status failed, nonzero exit
     * code stored and returned — never silently upgraded to passed.
     */
    public function test_a_failing_run_records_failed_status_and_exits_nonzero(): void
    {
        $this->bindRunnerFake(pass: false);

        $exitCode = $this->artisan(self::COMMAND)->run();

        $this->assertNotSame(0, $exitCode);

        $row = DB::table('backup_restore_evidence')->orderByDesc('id')->first();
        $this->assertNotNull($row);
        $this->assertSame('failed', $row->status);
        $this->assertNotSame(0, (int) $row->exit_code);
    }

    /**
     * Re-running the command inserts a second row rather than updating
     * the first — the evidence log is append-only, with no update/delete
     * path.
     */
    public function test_rerunning_the_command_appends_a_second_row_never_updates_the_first(): void
    {
        $this->bindRunnerFake(pass: true);
        $this->artisan(self::COMMAND)->assertExitCode(0);

        $this->bindRunnerFake(pass: false);
        $this->artisan(self::COMMAND)->run();

        $rows = DB::table('backup_restore_evidence')->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('passed', $rows[0]->status);
        $this->assertSame('failed', $rows[1]->status);
    }

    /**
     * Exactly one future persistence class is the frozen repository, and
     * it must never expose an update or delete path — the evidence log
     * is append-only.
     */
    public function test_evidence_repository_exposes_no_update_or_delete_path(): void
    {
        $repositoryClass = 'App\\Infrastructure\\Security\\Persistence\\BackupRestoreEvidenceRepository';

        $this->assertTrue(class_exists($repositoryClass), $repositoryClass.' must exist as the sole evidence persistence class.');
        $this->assertFalse(method_exists($repositoryClass, 'update'), $repositoryClass.' must not expose an update path.');
        $this->assertFalse(method_exists($repositoryClass, 'delete'), $repositoryClass.' must not expose a delete path.');
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
        $this->bindRunnerFakeClaimingPassDespiteFailedIntegrity();

        $exitCode = $this->artisan(self::COMMAND)->run();

        $this->assertNotSame(0, $exitCode, 'The command must not exit successfully when restored_integrity_ok is false, regardless of the runner\'s passed claim.');

        $row = DB::table('backup_restore_evidence')->orderByDesc('id')->first();
        $this->assertNotNull($row);
        $this->assertSame('failed', $row->status, 'The evidence row must be recorded as failed, not upgraded to passed, when restored_integrity_ok is false.');
        $this->assertNotSame(0, (int) $row->exit_code);
    }

    /**
     * A runner may itself be compromised or buggy and claim passed/exit-0
     * with matching hashes and row counts while omitting the mandatory
     * restored_integrity_ok key entirely. The application must not
     * default a missing signal to true — it must append a failed
     * evidence row and exit nonzero, exactly as it does for an explicit
     * restored_integrity_ok = false.
     */
    public function test_a_runner_claiming_pass_with_missing_restored_integrity_signal_is_recorded_as_failed(): void
    {
        $this->bindRunnerFakeClaimingPassWithMissingIntegritySignal();

        $exitCode = $this->artisan(self::COMMAND)->run();

        $this->assertNotSame(0, $exitCode, 'The command must not exit successfully when restored_integrity_ok is missing from the runner result, regardless of the runner\'s passed claim.');

        $row = DB::table('backup_restore_evidence')->orderByDesc('id')->first();
        $this->assertNotNull($row);
        $this->assertSame('failed', $row->status, 'The evidence row must be recorded as failed, not upgraded to passed, when restored_integrity_ok is missing.');
        $this->assertNotSame(0, (int) $row->exit_code);
    }
}
