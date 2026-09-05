<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Application\Security\Port\BackupRestoreDrillRunnerPort;
use App\Application\Security\Port\MediaBackupRestoreDrillRunnerPort;
use App\Domain\Security\BackupRestoreDriver;
use App\Infrastructure\Security\Execution\BackupRestoreDrillRunnerFactory;
use App\Infrastructure\Security\Execution\PostgresBackupRestoreDrillRunner;
use App\Infrastructure\Security\Execution\SqliteBackupRestoreDrillRunner;
use App\Infrastructure\Security\Execution\TarMediaBackupRestoreDrillRunner;
use Tests\TestCase;

/**
 * FF-199 (docs/124) — the runner is chosen by the DATABASE CONNECTION,
 * not by a flag. Production is `pgsql`; a runner that only knew SQLite
 * would throw there (it did, until this package) and the daily drill
 * would never produce a record. The factory is what the container
 * binding calls; this test locks the choice.
 *
 * Requirement IDs: SEC-BR-RUNNER-BY-CONNECTION-01.
 */
final class BackupRestoreDrillRunnerFactoryTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_sqlite_connection_with_a_real_file_yields_the_sqlite_runner(): void
    {
        $file = sys_get_temp_dir().DIRECTORY_SEPARATOR.'zabuno-factory-'.bin2hex(random_bytes(6)).'.sqlite';
        file_put_contents($file, '');
        $this->tempFiles[] = $file;

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $file);

        $runner = BackupRestoreDrillRunnerFactory::databaseRunnerFromConfig();

        $this->assertInstanceOf(SqliteBackupRestoreDrillRunner::class, $runner);
        $this->assertSame(BackupRestoreDriver::Sqlite, $runner->driver());
    }

    public function test_sqlite_in_memory_database_is_refused(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        $this->expectException(\RuntimeException::class);
        BackupRestoreDrillRunnerFactory::databaseRunnerFromConfig();
    }

    public function test_pgsql_connection_yields_the_postgres_runner_without_connecting(): void
    {
        config()->set('database.default', 'pgsql');
        config()->set('database.connections.pgsql.host', '127.0.0.1');
        config()->set('database.connections.pgsql.port', '1');
        config()->set('database.connections.pgsql.database', 'zabuno_never');
        config()->set('database.connections.pgsql.username', 'nobody');
        config()->set('database.connections.pgsql.password', 'nothing');

        $runner = BackupRestoreDrillRunnerFactory::databaseRunnerFromConfig();

        $this->assertInstanceOf(PostgresBackupRestoreDrillRunner::class, $runner);
        $this->assertSame(BackupRestoreDriver::Pgsql, $runner->driver());
    }

    public function test_any_other_connection_is_refused_explicitly(): void
    {
        config()->set('database.default', 'mysql');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/mysql/');
        BackupRestoreDrillRunnerFactory::databaseRunnerFromConfig();
    }

    public function test_the_container_resolves_the_database_port_through_the_factory(): void
    {
        config()->set('database.default', 'pgsql');
        config()->set('database.connections.pgsql.host', '127.0.0.1');
        config()->set('database.connections.pgsql.port', '1');

        $runner = $this->app->make(BackupRestoreDrillRunnerPort::class);

        $this->assertInstanceOf(PostgresBackupRestoreDrillRunner::class, $runner);
    }

    public function test_the_media_runner_targets_the_local_disk_root(): void
    {
        $runner = BackupRestoreDrillRunnerFactory::mediaRunnerFromConfig();

        $this->assertInstanceOf(TarMediaBackupRestoreDrillRunner::class, $runner);
        $this->assertInstanceOf(MediaBackupRestoreDrillRunnerPort::class, $this->app->make(MediaBackupRestoreDrillRunnerPort::class));
    }
}
