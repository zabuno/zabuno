<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Domain\Security\BackupRestoreTableManifest;
use App\Infrastructure\Security\Execution\PostgresBackupRestoreDrillRunner;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

/**
 * FF-199 (docs/124) — the PostgreSQL drill, MEASURED for real.
 *
 * Production runs PostgreSQL; until this test the only drill ever run
 * was the SQLite one, on a developer machine. This test runs the real
 * thing: `pg_dump --format=custom` of the frozen four tables from the
 * suite's PostgreSQL database, `pg_restore` into a throwaway database
 * on the same server, row counts and content digests compared, the
 * throwaway dropped.
 *
 * WHERE IT RUNS. Only where `DB_CONNECTION=pgsql` — the CI job
 * "Laravel test suite (PostgreSQL, deployment target)". On a machine
 * without PostgreSQL the result is not "passed", it is UNKNOWN, and the
 * test says so by skipping with that reason. In CI (`CI=true`) missing
 * `pg_dump`/`pg_restore` is a FAILURE, not a skip: a CI that silently
 * stops measuring would be worse than none.
 *
 * `DatabaseMigrations` rather than `RefreshDatabase`: the latter wraps
 * every test in a transaction, and rows inside an open transaction are
 * invisible to the separate `pg_dump` connection. The drill must see
 * committed rows, exactly as it will in production.
 *
 * Requirement IDs: SEC-BR-PG-ROUNDTRIP-01, SEC-BR-PG-NO-MUTATE-01,
 * SEC-BR-PG-ISOLATION-01, SEC-BR-PG-RESTORE-FAILURE-HONEST-01.
 */
final class PostgresBackupRestoreDrillTest extends TestCase
{
    use DatabaseMigrations;

    /** @var list<string> */
    private array $tempDirsToClean = [];

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'pgsql') {
            $this->markTestSkipped(
                'PostgreSQL bağlantısı yok: bu makinede sonuç BİLİNMİYOR ("geçti" değil). '
                .'CI, DB_CONNECTION=pgsql ile gerçek pg_dump/pg_restore üzerinde ölçer.'
            );
        }

        parent::setUp();
    }

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

    private function requireClientTools(): void
    {
        $finder = new ExecutableFinder;
        $missing = array_values(array_filter(['pg_dump', 'pg_restore'], static fn (string $tool): bool => $finder->find($tool) === null));

        if ($missing === []) {
            return;
        }

        if (getenv('CI') === 'true') {
            $this->fail('CI must measure the PostgreSQL drill; missing on PATH: '.implode(', ', $missing).' (see .github/workflows/ci.yml).');
        }

        $this->markTestSkipped('pg_dump/pg_restore yok: sonuç bilinmiyor. Eksik: '.implode(', ', $missing));
    }

    /**
     * @return array<string, mixed>
     */
    private function connection(): array
    {
        /** @var array<string, mixed> $config */
        $config = config('database.connections.pgsql');

        return $config;
    }

    private function seedRows(): int
    {
        $userA = (int) DB::table('users')->insertGetId([
            'name' => 'Ayşe', 'email' => 'ayse@example.test', 'password' => 'x', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $userB = (int) DB::table('users')->insertGetId([
            'name' => 'Barış', 'email' => 'baris@example.test', 'password' => 'x', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $workspace = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Restoran Kadıköy', 'slug' => 'kadikoy', 'state' => 'active', 'created_by' => $userA, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('workspace_memberships')->insert([
            ['workspace_id' => $workspace, 'user_id' => $userA, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()],
            ['workspace_id' => $workspace, 'user_id' => $userB, 'role' => 'editor', 'created_at' => now(), 'updated_at' => now()],
        ]);
        // Brand and location are NOT in the frozen manifest; they exist
        // only so that `menus` has a valid foreign key to point at. The
        // drill's restore therefore lands a `menus` row whose
        // `location_id` references a table that was never dumped — the
        // runner must restore pre-data and data only (docs/124).
        $brand = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspace, 'name' => 'Restoran Kadıköy', 'slug' => 'kadikoy-brand', 'locale' => 'tr',
            'timezone' => 'Europe/Istanbul', 'currency' => 'TRY', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $location = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspace, 'brand_id' => $brand, 'display_name' => 'Merkez', 'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul', 'city' => 'İstanbul', 'address_line1' => 'Bahariye Cd. No:1',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('menus')->insert([
            'public_key' => 'drillmenu01', 'workspace_id' => $workspace, 'location_id' => $location, 'name' => 'Ana Menü',
            'state' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return 2 + 1 + 2 + 1;
    }

    private function countManifestRows(): int
    {
        $total = 0;
        foreach (BackupRestoreTableManifest::tables() as $table) {
            $total += (int) DB::table($table)->count();
        }

        return $total;
    }

    private function throwawayDatabaseCount(): int
    {
        return (int) DB::selectOne("SELECT count(*) AS c FROM pg_database WHERE datname LIKE 'zabuno_drill_%'")->c;
    }

    public function test_pg_dump_and_pg_restore_round_trip_restores_every_row_of_the_frozen_manifest(): void
    {
        $this->requireClientTools();
        $expected = $this->seedRows();
        $this->assertSame($expected, $this->countManifestRows());
        $workRoot = $this->makeUniqueTempDir();

        $runner = new PostgresBackupRestoreDrillRunner($this->connection(), $workRoot);
        $result = $runner->run(BackupRestoreTableManifest::tables());

        $this->assertTrue($result['passed'], $result['output']);
        $this->assertTrue($result['measured']);
        $this->assertSame(0, $result['exit_code']);
        $this->assertTrue($result['restored_integrity_ok']);
        $this->assertSame($expected, $result['source_row_count']);
        $this->assertSame($expected, $result['restored_row_count']);
        $this->assertSame(64, strlen($result['backup_sha256']));
        $this->assertSame($result['backup_sha256'], $result['restored_db_sha256'], 'Source and restored content digests must match.');
        $this->assertGreaterThan(0, $result['backup_bytes'], 'A custom-format archive of seeded tables cannot be empty.');
        $this->assertGreaterThanOrEqual(0, $result['backup_ms']);
        $this->assertGreaterThanOrEqual(0, $result['restore_ms']);

        // Isolation: the source is untouched, the throwaway is gone, the
        // work root is empty.
        $this->assertSame($expected, $this->countManifestRows());
        $this->assertSame(0, $this->throwawayDatabaseCount());
        clearstatcache();
        $this->assertSame([], array_values(array_diff(scandir($workRoot) ?: [], ['.', '..'])));
    }

    /**
     * A restore that breaks is a FAILED drill (measured, nonzero exit),
     * not an unknown one — and it still leaves no throwaway database
     * behind. The wrapper stands in for pg_restore and refuses to work.
     */
    public function test_a_failing_restore_is_recorded_as_failed_and_still_cleans_up(): void
    {
        $this->requireClientTools();
        $this->seedRows();
        $workRoot = $this->makeUniqueTempDir();

        $wrapperDir = $this->makeUniqueTempDir();
        $wrapper = $wrapperDir.DIRECTORY_SEPARATOR.'pg_restore';
        file_put_contents($wrapper, "#!/bin/sh\nif [ \"\$1\" = \"--version\" ]; then echo 'pg_restore (PostgreSQL) 17.0'; exit 0; fi\necho 'simulated restore failure' >&2\nexit 3\n");
        chmod($wrapper, 0700);

        $runner = new PostgresBackupRestoreDrillRunner($this->connection(), $workRoot, null, $wrapper);
        $result = $runner->run(BackupRestoreTableManifest::tables());

        $this->assertFalse($result['passed']);
        $this->assertTrue($result['measured'], 'The dump ran and the restore was attempted: this drill WAS measured.');
        $this->assertNotSame(0, $result['exit_code']);
        $this->assertFalse($result['restored_integrity_ok']);
        $this->assertSame(0, $this->throwawayDatabaseCount());
        clearstatcache();
        $this->assertSame([], array_values(array_diff(scandir($workRoot) ?: [], ['.', '..'])));
    }
}
