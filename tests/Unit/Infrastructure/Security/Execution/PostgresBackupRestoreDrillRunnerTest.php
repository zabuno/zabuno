<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Security\Execution;

use App\Domain\Security\BackupRestoreDriver;
use PHPUnit\Framework\TestCase;

/**
 * FF-199 RED (docs/124) — the PostgreSQL drill runner, server-free
 * contract. What this test can prove WITHOUT a PostgreSQL server: the
 * runner implements the port, names its driver, refuses any table list
 * other than the frozen manifest, and — the owner-facing promise — when
 * `pg_dump`/`pg_restore` are not there it says "unknown" (not measured,
 * exit 127) instead of "passed". The real dump/restore round trip with
 * row-count and content-digest comparison is measured in
 * tests/Feature/Security/PostgresBackupRestoreDrillTest.php, which
 * only runs where a PostgreSQL server exists (CI).
 *
 * Requirement IDs: SEC-BR-PG-PORT-01, SEC-BR-PG-FROZEN-TABLES-01,
 * SEC-BR-PG-TOOLS-UNKNOWN-01, SEC-BR-PG-NO-SECRETS-01,
 * SEC-BR-PG-CLEANUP-01.
 */
final class PostgresBackupRestoreDrillRunnerTest extends TestCase
{
    private const RUNNER_CLASS = 'App\\Infrastructure\\Security\\Execution\\PostgresBackupRestoreDrillRunner';

    private const PORT = 'App\\Application\\Security\\Port\\BackupRestoreDrillRunnerPort';

    private const TABLES = ['users', 'workspaces', 'workspace_memberships', 'menus'];

    /** @var list<string> */
    private array $tempDirsToClean = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirsToClean as $dir) {
            $this->removeDirectoryRecursively($dir);
        }
        $this->tempDirsToClean = [];

        parent::tearDown();
    }

    private function removeDirectoryRecursively(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.DIRECTORY_SEPARATOR.$item;
            is_dir($path) ? $this->removeDirectoryRecursively($path) : @unlink($path);
        }

        @rmdir($dir);
    }

    private function makeUniqueTempDir(): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'zabuno-pg-drill-'.bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($dir, 0700, true));
        $this->tempDirsToClean[] = $dir;

        return $dir;
    }

    /**
     * A connection that points nowhere: the binary check must come
     * FIRST, so nothing below ever opens a socket.
     *
     * @return array<string, mixed>
     */
    private function unreachableConnection(): array
    {
        return [
            'host' => '127.0.0.1',
            'port' => 1,
            'database' => 'zabuno_never',
            'username' => 'nobody',
            'password' => 'nothing',
        ];
    }

    public function test_runner_class_implements_the_port_and_names_its_driver(): void
    {
        $this->assertTrue(class_exists(self::RUNNER_CLASS), self::RUNNER_CLASS.' must exist.');
        $this->assertContains(self::PORT, class_implements(self::RUNNER_CLASS) ?: []);

        $runner = new (self::RUNNER_CLASS)($this->unreachableConnection(), $this->makeUniqueTempDir());

        $this->assertSame(BackupRestoreDriver::Pgsql, $runner->driver());
    }

    public function test_runner_rejects_a_table_list_other_than_the_frozen_manifest(): void
    {
        $runner = new (self::RUNNER_CLASS)($this->unreachableConnection(), $this->makeUniqueTempDir());

        $this->expectException(\InvalidArgumentException::class);
        $runner->run(['users', 'workspaces']);
    }

    /**
     * No pg_dump: the drill was never taken. The result must say so —
     * not measured, exit 127 ("command not found"), a message that
     * names the missing tool — and must never be "passed".
     */
    public function test_missing_pg_dump_is_reported_as_unknown_not_as_a_verdict(): void
    {
        $runner = new (self::RUNNER_CLASS)(
            $this->unreachableConnection(),
            $this->makeUniqueTempDir(),
            '/nonexistent/zabuno/pg_dump',
            '/nonexistent/zabuno/pg_restore',
        );

        $result = $runner->run(self::TABLES);

        $this->assertFalse($result['passed']);
        $this->assertFalse($result['measured']);
        $this->assertSame(127, $result['exit_code']);
        $this->assertFalse($result['restored_integrity_ok']);
        $this->assertStringContainsString('pg_dump', $result['output']);
        $this->assertSame(0, $result['source_row_count']);
        $this->assertSame(0, $result['restored_row_count']);
        $this->assertSame(0, $result['backup_bytes']);
        $this->assertSame(str_repeat('0', 64), $result['backup_sha256']);
        $this->assertSame(str_repeat('0', 64), $result['restored_db_sha256']);
    }

    public function test_missing_pg_restore_alone_is_also_unknown(): void
    {
        $dir = $this->makeUniqueTempDir();
        $fakeDump = $dir.DIRECTORY_SEPARATOR.'pg_dump';
        file_put_contents($fakeDump, "#!/bin/sh\necho 'pg_dump (PostgreSQL) 17.0'\n");
        chmod($fakeDump, 0700);

        $runner = new (self::RUNNER_CLASS)(
            $this->unreachableConnection(),
            $dir,
            $fakeDump,
            '/nonexistent/zabuno/pg_restore',
        );

        $result = $runner->run(self::TABLES);

        $this->assertFalse($result['passed']);
        $this->assertFalse($result['measured']);
        $this->assertSame(127, $result['exit_code']);
        $this->assertStringContainsString('pg_restore', $result['output']);
    }

    /**
     * The result never exposes a path, a UUID, a DSN or the connection
     * (which carries the production password) under any key.
     */
    public function test_result_never_exposes_a_path_uuid_dsn_or_connection_key(): void
    {
        $runner = new (self::RUNNER_CLASS)(
            $this->unreachableConnection(),
            $this->makeUniqueTempDir(),
            '/nonexistent/zabuno/pg_dump',
            '/nonexistent/zabuno/pg_restore',
        );

        $result = $runner->run(self::TABLES);

        foreach (array_keys($result) as $key) {
            $this->assertDoesNotMatchRegularExpression('/path|uuid|pdo|connection|dsn|password/i', $key);
        }

        $this->assertStringNotContainsString('nothing', $result['output'], 'The password must never appear in the output.');
    }

    /**
     * An unknown result leaves nothing behind in the work root.
     */
    public function test_an_unknown_result_leaves_no_residue_in_the_work_root(): void
    {
        $workRoot = $this->makeUniqueTempDir();

        $runner = new (self::RUNNER_CLASS)(
            $this->unreachableConnection(),
            $workRoot,
            '/nonexistent/zabuno/pg_dump',
            '/nonexistent/zabuno/pg_restore',
        );
        $runner->run(self::TABLES);

        clearstatcache();
        $this->assertSame([], array_values(array_diff(scandir($workRoot) ?: [], ['.', '..'])));
    }
}
