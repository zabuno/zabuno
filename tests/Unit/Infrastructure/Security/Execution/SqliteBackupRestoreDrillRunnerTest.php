<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Security\Execution;

use PHPUnit\Framework\TestCase;

/**
 * S1-WP07 RED — the concrete backup/restore drill runner
 * (App\Infrastructure\Security\Execution\SqliteBackupRestoreDrillRunner)
 * must perform a real, isolated SQLite3 online-backup and file-copy
 * restore against a caller-supplied database file, never the live
 * application database: this test always constructs the runner against
 * a uniquely created temp SQLite file it seeds itself. Nothing under
 * App\Infrastructure\Security\Execution\SqliteBackupRestoreDrillRunner
 * exists yet, so every test below fails RED on class resolution, not on
 * assertion logic.
 *
 * Requirement IDs: SEC-BR-RUNNER-ISOLATED-01, SEC-BR-RUNNER-NO-MUTATE-01,
 * SEC-BR-RUNNER-INTEGRITY-01, SEC-BR-RUNNER-CLEANUP-01,
 * SEC-BR-RUNNER-FAIL-CLOSED-01.
 */
final class SqliteBackupRestoreDrillRunnerTest extends TestCase
{
    private const RUNNER_CLASS = 'App\\Infrastructure\\Security\\Execution\\SqliteBackupRestoreDrillRunner';

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

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->removeDirectoryRecursively($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    /**
     * Creates a uniquely named temp directory this test owns exclusively,
     * so the drill never touches any path shared with another process or
     * test.
     */
    private function makeUniqueTempDir(): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'zabuno-br-drill-'.bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($dir, 0700, true));
        $this->tempDirsToClean[] = $dir;

        return $dir;
    }

    /**
     * Builds a fresh temp SQLite database file seeded with the four
     * frozen tables and a handful of rows in each — never the live
     * application database.
     */
    private function makeSeededSourceDatabase(string $dir): string
    {
        $path = $dir.DIRECTORY_SEPARATOR.'source.sqlite';

        $pdo = new \PDO('sqlite:'.$path);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE workspaces (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE workspace_memberships (id INTEGER PRIMARY KEY, workspace_id INTEGER NOT NULL, user_id INTEGER NOT NULL)');
        $pdo->exec('CREATE TABLE menus (id INTEGER PRIMARY KEY, workspace_id INTEGER NOT NULL, title TEXT NOT NULL)');

        $pdo->exec("INSERT INTO users (email) VALUES ('a@example.test'), ('b@example.test')");
        $pdo->exec("INSERT INTO workspaces (name) VALUES ('Restoran A')");
        $pdo->exec('INSERT INTO workspace_memberships (workspace_id, user_id) VALUES (1, 1), (1, 2)');
        $pdo->exec("INSERT INTO menus (workspace_id, title) VALUES (1, 'Ana Menu')");

        unset($pdo);

        return $path;
    }

    public function test_runner_class_implements_the_port(): void
    {
        $this->assertTrue(class_exists(self::RUNNER_CLASS), self::RUNNER_CLASS.' must exist.');
        $this->assertTrue(interface_exists(self::PORT), self::PORT.' must exist.');
        $this->assertContains(self::PORT, class_implements(self::RUNNER_CLASS) ?: []);
    }

    /**
     * A passing drill reports zero exit, matching row counts across the
     * exact four frozen tables, and matching backup/restored content
     * hashes.
     */
    public function test_a_successful_drill_reports_passed_with_matching_counts_and_hashes(): void
    {
        $dir = $this->makeUniqueTempDir();
        $sourcePath = $this->makeSeededSourceDatabase($dir);

        $runner = new (self::RUNNER_CLASS)($sourcePath);
        $result = $runner->run(self::TABLES);

        $this->assertTrue($result['passed']);
        $this->assertSame(0, $result['exit_code']);
        $this->assertGreaterThanOrEqual(0, $result['duration_ms']);
        $this->assertSame(2 + 1 + 2 + 1, $result['source_row_count']);
        $this->assertSame($result['source_row_count'], $result['restored_row_count']);
        $this->assertSame(64, strlen($result['backup_sha256']));
        $this->assertSame(64, strlen($result['restored_db_sha256']));
        $this->assertSame($result['backup_sha256'], $result['restored_db_sha256']);
        $this->assertTrue($result['restored_integrity_ok']);
    }

    /**
     * A completed run reports whether the restored copy passed
     * PRAGMA integrity_check via a boolean field, and never exposes any
     * source/backup/restored/temp path, UUID, or PDO/connection/DSN key.
     */
    public function test_result_never_exposes_a_path_uuid_or_connection_key(): void
    {
        $dir = $this->makeUniqueTempDir();
        $sourcePath = $this->makeSeededSourceDatabase($dir);

        $runner = new (self::RUNNER_CLASS)($sourcePath);
        $result = $runner->run(self::TABLES);

        $this->assertArrayHasKey('restored_integrity_ok', $result);
        $this->assertIsBool($result['restored_integrity_ok']);

        foreach (array_keys($result) as $key) {
            $this->assertDoesNotMatchRegularExpression(
                '/path|uuid|pdo|connection|dsn/i',
                $key,
                "Result key '{$key}' must not expose a path, UUID, or connection identifier."
            );
        }
    }

    /**
     * The drill never overwrites or modifies the source file it was
     * pointed at — its content hash before and after the run is
     * identical.
     */
    public function test_source_file_is_never_overwritten_or_modified_by_the_drill(): void
    {
        $dir = $this->makeUniqueTempDir();
        $sourcePath = $this->makeSeededSourceDatabase($dir);
        $hashBefore = hash_file('sha256', $sourcePath);

        $runner = new (self::RUNNER_CLASS)($sourcePath);
        $runner->run(self::TABLES);

        $hashAfter = hash_file('sha256', $sourcePath);
        $this->assertSame($hashBefore, $hashAfter, 'The source database file must be byte-identical before and after the drill.');
    }

    /**
     * The online SQLite3 backup is restored into a separate, internally
     * managed file copy — never opened in place over the source file —
     * and the runner reports that the restored copy passed
     * PRAGMA integrity_check via a boolean field, never a path.
     */
    public function test_online_backup_is_restored_to_a_separate_file_copy_that_passes_integrity_check(): void
    {
        $dir = $this->makeUniqueTempDir();
        $sourcePath = $this->makeSeededSourceDatabase($dir);

        $runner = new (self::RUNNER_CLASS)($sourcePath);
        $result = $runner->run(self::TABLES);

        $this->assertTrue($result['passed']);
        $this->assertArrayNotHasKey('restored_db_path', $result);
        $this->assertArrayHasKey('restored_integrity_ok', $result);
        $this->assertTrue($result['restored_integrity_ok']);
    }

    /**
     * The runner counts rows across exactly the four frozen tables in
     * the frozen order, never a caller-influenced table list.
     */
    public function test_runner_rejects_a_table_list_other_than_the_frozen_manifest(): void
    {
        $dir = $this->makeUniqueTempDir();
        $sourcePath = $this->makeSeededSourceDatabase($dir);

        $runner = new (self::RUNNER_CLASS)($sourcePath);

        $this->expectException(\InvalidArgumentException::class);
        $runner->run(['users', 'workspaces']);
    }

    /**
     * Temp artifacts (the online backup file and the restored file copy)
     * are removed after a successful run — the drill leaves no residue
     * outside the source file it was pointed at. The runner never
     * exposes those temp artifact paths, so cleanup is asserted purely
     * by observing the source directory's entries are unchanged.
     */
    public function test_temp_artifacts_are_cleaned_up_after_a_successful_run(): void
    {
        $dir = $this->makeUniqueTempDir();
        $sourcePath = $this->makeSeededSourceDatabase($dir);
        $entriesBefore = scandir($dir);

        $runner = new (self::RUNNER_CLASS)($sourcePath);
        $result = $runner->run(self::TABLES);
        $this->assertTrue($result['passed']);

        clearstatcache();
        $entriesAfter = scandir($dir);
        $this->assertSame($entriesBefore, $entriesAfter, 'No artifact may be left behind in the source directory.');
    }

    /**
     * Temp artifacts are cleaned up even when the drill fails partway
     * through — for example, integrity verification fails because the
     * source database was corrupted before the drill ran.
     */
    public function test_temp_artifacts_are_cleaned_up_after_a_failed_run(): void
    {
        $dir = $this->makeUniqueTempDir();
        $sourcePath = $dir.DIRECTORY_SEPARATOR.'corrupt.sqlite';
        file_put_contents($sourcePath, 'this is not a valid sqlite database file');

        $runner = new (self::RUNNER_CLASS)($sourcePath);
        $result = $runner->run(self::TABLES);

        $this->assertFalse($result['passed']);
        $this->assertNotSame(0, $result['exit_code']);

        $leftoverFiles = array_values(array_diff(scandir($dir) ?: [], ['.', '..', 'corrupt.sqlite']));
        $this->assertSame([], $leftoverFiles, 'No temp artifact may survive a failed drill either.');
    }

    /**
     * A non-file SQLite target (e.g. ":memory:", or a path that does not
     * exist) is not a supported drill source — the runner fails closed
     * rather than silently skipping the backup step.
     */
    public function test_unsupported_non_file_sqlite_target_fails_closed(): void
    {
        $runner = new (self::RUNNER_CLASS)(':memory:');

        $result = $runner->run(self::TABLES);

        $this->assertFalse($result['passed']);
        $this->assertNotSame(0, $result['exit_code']);
    }

    public function test_missing_source_database_file_fails_closed(): void
    {
        $dir = $this->makeUniqueTempDir();
        $missingPath = $dir.DIRECTORY_SEPARATOR.'does-not-exist.sqlite';

        $runner = new (self::RUNNER_CLASS)($missingPath);
        $result = $runner->run(self::TABLES);

        $this->assertFalse($result['passed']);
        $this->assertNotSame(0, $result['exit_code']);
    }
}
