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
 * `security:evidence:tenant-isolation` must run the frozen six-file
 * suite through an injected runner port and append-only record a new
 * evidence row, exiting 0 only on pass. Neither the command nor its
 * runner port exists yet, so every test below fails RED on missing
 * class/binding, not on assertion logic.
 *
 * RUNNER_PORT is an interface, not a concrete class. Mockery::mock() can
 * create a mock for an interface that does not exist yet, and container
 * instance() binding is keyed by string, so binding never triggers
 * autoloading of the not-yet-written production port — mirrors
 * IyzicoSandboxWebhookTest::bindGatewayFake(). The bound Mockery fake
 * never spawns PHPUnit recursively; it only returns a canned result
 * array.
 *
 * Requirement IDs: SEC-EVID-CMD-NO-OPTIONS-01, SEC-EVID-CMD-APPEND-01,
 * SEC-EVID-CMD-FAIL-HONEST-01, SEC-EVID-CMD-EXIT-CODE-01.
 */
final class TenantIsolationEvidenceCommandTest extends TestCase
{
    use RefreshDatabase;

    private const RUNNER_PORT = 'App\\Application\\Security\\Port\\TenantIsolationSuiteRunnerPort';

    private const COMMAND = 'security:evidence:tenant-isolation';

    private const FROZEN_SUITE_PATHS = [
        'tests/Feature/Media/MediaTenantIsolationTest.php',
        'tests/Feature/MenuCatalog/MenuApiTenantEscapeTest.php',
        'tests/Feature/MenuCatalog/MenuCatalogTenantEscapeTest.php',
        'tests/Feature/Publication/PublicationTenantIsolationTest.php',
        'tests/Feature/QrDestination/BulkQrTenantIsolationTest.php',
        'tests/Feature/QrDestination/QrDestinationTenantIsolationTest.php',
    ];

    /**
     * @return array{passed: bool, exit_code: int, duration_ms: int, output: string}
     */
    private function runResult(bool $pass, int $durationMs = 777, string $rawOutput = 'OK (6 tests)'): array
    {
        return [
            'passed' => $pass,
            'exit_code' => $pass ? 0 : 1,
            'duration_ms' => $durationMs,
            'output' => $rawOutput,
        ];
    }

    private function bindRunnerFake(bool $pass, int $durationMs = 777, string $rawOutput = 'OK (6 tests)'): MockInterface
    {
        $fake = Mockery::mock(self::RUNNER_PORT);
        $fake->shouldReceive('run')
            ->with(self::FROZEN_SUITE_PATHS)
            ->andReturn($this->runResult($pass, $durationMs, $rawOutput));

        app()->instance(self::RUNNER_PORT, $fake);

        return $fake;
    }

    public function test_command_class_and_runner_port_exist(): void
    {
        $this->assertTrue(interface_exists(self::RUNNER_PORT), self::RUNNER_PORT.' must exist as an injected port interface.');
    }

    /**
     * No user-supplied pass/status/file options exist on this command —
     * the outcome is derived entirely from the injected runner result,
     * never from a caller-controlled flag.
     */
    public function test_command_accepts_no_pass_status_or_file_options(): void
    {
        $this->bindRunnerFake(pass: true);

        $exitCode = $this->artisan(self::COMMAND, ['--status' => 'passed'])->run();

        $this->assertSame(1, $exitCode, 'Passing an unknown --status option must fail the command, not select an outcome.');
    }

    public function test_a_passing_run_appends_one_evidence_row_and_exits_zero(): void
    {
        $this->bindRunnerFake(pass: true);

        $this->assertSame(0, DB::table('tenant_isolation_evidence')->count());

        $this->artisan(self::COMMAND)->assertExitCode(0);

        $this->assertSame(1, DB::table('tenant_isolation_evidence')->count());

        $row = DB::table('tenant_isolation_evidence')->first();
        $this->assertSame('tenant_isolation', $row->key);
        $this->assertSame('passed', $row->status);
        $this->assertSame('automated_local_feature_tests', $row->scope);
        $this->assertSame('phpunit', $row->runner);
        $this->assertSame(0, (int) $row->exit_code);
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

        $row = DB::table('tenant_isolation_evidence')->orderByDesc('id')->first();
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

        $rows = DB::table('tenant_isolation_evidence')->orderBy('id')->get();
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
        $repositoryClass = 'App\\Infrastructure\\Security\\Persistence\\TenantIsolationEvidenceRepository';

        $this->assertTrue(class_exists($repositoryClass), $repositoryClass.' must exist as the sole evidence persistence class.');
        $this->assertFalse(method_exists($repositoryClass, 'update'), $repositoryClass.' must not expose an update path.');
        $this->assertFalse(method_exists($repositoryClass, 'delete'), $repositoryClass.' must not expose a delete path.');
    }

    /**
     * The runner is invoked with exactly the frozen six suite paths —
     * never a caller-influenced file list. Enforced above via
     * shouldReceive('run')->with(self::FROZEN_SUITE_PATHS); a call with
     * any other argument list makes the mock expectation unmet and fails
     * the test.
     */
    public function test_command_invokes_runner_with_exactly_the_frozen_six_suite_paths(): void
    {
        $fake = $this->bindRunnerFake(pass: true);

        $this->artisan(self::COMMAND)->run();

        $fake->shouldHaveReceived('run')->once()->with(self::FROZEN_SUITE_PATHS);
    }
}
