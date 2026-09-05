<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Execution;

use App\Application\Security\Port\BackupRestoreDrillRunnerPort;
use App\Domain\Security\BackupRestoreDriver;
use App\Domain\Security\BackupRestoreTableManifest;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * PostgreSQL yedek/geri yükleme tatbikatı — üretim motorunda (docs/124).
 *
 * NE YAPAR. Dondurulmuş dört tabloyu `pg_dump --format=custom` ile
 * arşivler, aynı sunucuda `zabuno_drill_<rastgele>` adlı GEÇİCİ bir
 * veritabanı açar, arşivi `pg_restore` ile oraya yükler, tablo listesini,
 * satır sayılarını ve satır içeriği özetlerini kaynakla karşılaştırır,
 * geçici veritabanını düşürür ve arşivi siler. Kaynağa tek bir yazma
 * yoktur; geçici veritabanı yalnız bu koşucunun ürettiği adla ve yalnız
 * o desene uyuyorsa düşürülür.
 *
 * TUTARLILIK. Satır sayısı ve içerik özeti, `pg_dump`'ın gördüğü anlık
 * görüntüyle AYNI anlık görüntüden okunur: koşucu REPEATABLE READ bir
 * işlem açar, `pg_export_snapshot()` ile dışa aktarır, sayımı o işlemde
 * yapar ve `pg_dump --snapshot=` ile aynı görüntüyü dökümler. Yoksa canlı
 * bir sunucuda sayımla döküm arasına giren tek bir INSERT tatbikatı
 * "başarısız" gösterirdi.
 *
 * YALNIZ PRE-DATA + DATA. Manifest bir alt kümedir: `menus.location_id`
 * manifest dışındaki `locations` tablosuna başvurur. Post-data bölümü
 * (yabancı anahtarlar, indeksler) boş bir geçici veritabanında o tabloyu
 * bulamaz ve geri yükleme kırılırdı. Tatbikatın ölçtüğü şey satırların
 * geri gelmesidir; kısıtların geri gelmesi tam veritabanı dökümünün işidir
 * ve iddia metni bunu açıkça söyler.
 *
 * BİLİNMİYOR ≠ BAŞARISIZ. `pg_dump`/`pg_restore` yoksa ya da istemci
 * sunucudan eskiyse (pg_dump daha yeni bir sunucuyu reddeder) tatbikat
 * hiç denenmemiştir: `measured` = false, çıkış 127/126, "geçti" değil.
 */
final class PostgresBackupRestoreDrillRunner implements BackupRestoreDrillRunnerPort
{
    private const DEFAULT_WORK_ROOT_NAME = 'zabuno-backup-restore-drill';

    private const TEMP_CHILD_PREFIX = 'pg-drill-';

    private const THROWAWAY_PREFIX = 'zabuno_drill_';

    private const THROWAWAY_PATTERN = '/^zabuno_drill_[0-9a-f]{16}$/';

    private const PROCESS_TIMEOUT_SECONDS = 3600;

    /** Gerekli araç yok: tatbikat denenemedi. */
    private const EXIT_TOOL_MISSING = 127;

    /** Ön koşul sağlanmadı (sürüm uyumsuz vb.): tatbikat denenemedi. */
    private const EXIT_PRECONDITION = 126;

    /**
     * @param  array<string, mixed>  $connection  host, port, database, username, password, sslmode
     */
    public function __construct(
        private readonly array $connection,
        private readonly ?string $workRoot = null,
        private readonly ?string $pgDumpBinary = null,
        private readonly ?string $pgRestoreBinary = null,
    ) {}

    public function driver(): BackupRestoreDriver
    {
        return BackupRestoreDriver::Pgsql;
    }

    /**
     * @param  list<string>  $tables
     * @return array{passed: bool, exit_code: int, duration_ms: int, output: string, backup_sha256: string, restored_db_sha256: string, source_row_count: int, restored_row_count: int, restored_integrity_ok: bool, measured: bool, backup_bytes: int, backup_ms: int, restore_ms: int}
     */
    public function run(array $tables): array
    {
        if ($tables !== BackupRestoreTableManifest::tables()) {
            throw new InvalidArgumentException('BackupRestoreDrillRunnerPort::run() only accepts the frozen table manifest.');
        }

        $startedAt = hrtime(true);
        $tempChild = null;
        $throwaway = null;
        $source = null;
        $serverMajor = null;

        try {
            $pgDump = $this->locate('pg_dump', $this->pgDumpBinary);
            if ($pgDump === null) {
                return $this->unmeasured(self::EXIT_TOOL_MISSING, 'pg_dump not found on PATH; drill result unknown', $startedAt);
            }

            $pgRestore = $this->locate('pg_restore', $this->pgRestoreBinary);
            if ($pgRestore === null) {
                return $this->unmeasured(self::EXIT_TOOL_MISSING, 'pg_restore not found on PATH; drill result unknown', $startedAt);
            }

            $source = $this->connect($this->sourceDatabase());
            $serverMajor = $this->serverMajorVersion($source);
            $clientMajor = $this->clientMajorVersion($pgDump);

            if ($clientMajor !== null && $clientMajor < $serverMajor) {
                return $this->unmeasured(
                    self::EXIT_PRECONDITION,
                    sprintf('pg_dump major version %d is older than the server major version %d; pg_dump refuses newer servers, drill result unknown', $clientMajor, $serverMajor),
                    $startedAt,
                );
            }

            $workRoot = $this->resolveWorkRoot();
            if (! is_dir($workRoot) && ! @mkdir($workRoot, 0700, true) && ! is_dir($workRoot)) {
                throw new RuntimeException('Unable to prepare the backup/restore drill work root.');
            }

            $tempChild = $workRoot.DIRECTORY_SEPARATOR.self::TEMP_CHILD_PREFIX.bin2hex(random_bytes(16));
            if (! mkdir($tempChild, 0700, true)) {
                throw new RuntimeException('Unable to create a guarded temp child for the backup/restore drill.');
            }

            $archivePath = $tempChild.DIRECTORY_SEPARATOR.'backup.dump';

            // --- Yedek: sayım, özet ve döküm aynı anlık görüntüden -------
            $backupStartedAt = hrtime(true);
            $source->beginTransaction();
            $source->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $snapshotName = (string) $source->query('SELECT pg_export_snapshot()')->fetchColumn();
            $sourceRowCount = $this->countRows($source, $tables);
            $sourceDigest = $this->contentDigest($source, $tables);

            $dumpCommand = [
                $pgDump,
                '--format=custom',
                '--no-owner',
                '--no-privileges',
                '--snapshot='.$snapshotName,
                '--file='.$archivePath,
            ];
            foreach ($tables as $table) {
                $dumpCommand[] = '--table=public.'.$table;
            }
            $dumpCommand = [...$dumpCommand, ...$this->clientArguments(), '--dbname='.$this->sourceDatabase()];

            $this->runProcess($dumpCommand, 'pg_dump');
            $source->commit();
            $backupMs = $this->elapsedMs($backupStartedAt);

            $backupBytes = filesize($archivePath);
            if ($backupBytes === false || $backupBytes === 0) {
                throw new RuntimeException('pg_dump produced an empty archive.');
            }

            // --- Geri yükleme: geçici veritabanına, izole -----------------
            $restoreStartedAt = hrtime(true);
            $throwaway = self::THROWAWAY_PREFIX.bin2hex(random_bytes(8));
            $source->exec('CREATE DATABASE '.$this->quoteIdentifier($throwaway).' TEMPLATE template0');

            $this->runProcess([
                $pgRestore,
                '--no-owner',
                '--no-privileges',
                '--single-transaction',
                '--section=pre-data',
                '--section=data',
                ...$this->clientArguments(),
                '--dbname='.$throwaway,
                $archivePath,
            ], 'pg_restore');

            $restored = $this->connect($throwaway);
            $expectedTables = $tables;
            sort($expectedTables);
            $tablesMatch = $this->publicTables($restored) === $expectedTables;
            $restoredRowCount = $tablesMatch ? $this->countRows($restored, $tables) : 0;
            $restoredDigest = $tablesMatch ? $this->contentDigest($restored, $tables) : str_repeat('0', 64);
            $restored = null;
            $restoreMs = $this->elapsedMs($restoreStartedAt);

            $passed = $tablesMatch
                && $sourceRowCount === $restoredRowCount
                && hash_equals($sourceDigest, $restoredDigest);

            return [
                'passed' => $passed,
                'exit_code' => $passed ? 0 : 1,
                'duration_ms' => $this->elapsedMs($startedAt),
                'output' => ($passed ? 'backup/restore drill passed' : 'backup/restore drill failed table list, row count or content digest verification')
                    .sprintf(' (server %d, archive sha256 %s, %d bytes)', $serverMajor, (string) hash_file('sha256', $archivePath), $backupBytes),
                'backup_sha256' => $sourceDigest,
                'restored_db_sha256' => $restoredDigest,
                'source_row_count' => $sourceRowCount,
                'restored_row_count' => $restoredRowCount,
                'restored_integrity_ok' => $tablesMatch,
                'measured' => true,
                'backup_bytes' => $backupBytes,
                'backup_ms' => $backupMs,
                'restore_ms' => $restoreMs,
            ];
        } catch (Throwable $e) {
            return [
                'passed' => false,
                'exit_code' => 1,
                'duration_ms' => $this->elapsedMs($startedAt),
                'output' => 'backup/restore drill failed: '.$this->redact($e->getMessage()),
                'backup_sha256' => str_repeat('0', 64),
                'restored_db_sha256' => str_repeat('0', 64),
                'source_row_count' => 0,
                'restored_row_count' => 0,
                'restored_integrity_ok' => false,
                'measured' => true,
                'backup_bytes' => 0,
                'backup_ms' => 0,
                'restore_ms' => 0,
            ];
        } finally {
            $this->dropThrowaway($source, $throwaway, $serverMajor);
            $source = null;
            $this->cleanupTempChild($tempChild);
        }
    }

    /**
     * @return array{passed: bool, exit_code: int, duration_ms: int, output: string, backup_sha256: string, restored_db_sha256: string, source_row_count: int, restored_row_count: int, restored_integrity_ok: bool, measured: bool, backup_bytes: int, backup_ms: int, restore_ms: int}
     */
    private function unmeasured(int $exitCode, string $output, int|float $startedAt): array
    {
        return [
            'passed' => false,
            'exit_code' => $exitCode,
            'duration_ms' => $this->elapsedMs($startedAt),
            'output' => $output,
            'backup_sha256' => str_repeat('0', 64),
            'restored_db_sha256' => str_repeat('0', 64),
            'source_row_count' => 0,
            'restored_row_count' => 0,
            'restored_integrity_ok' => false,
            'measured' => false,
            'backup_bytes' => 0,
            'backup_ms' => 0,
            'restore_ms' => 0,
        ];
    }

    private function locate(string $tool, ?string $configured): ?string
    {
        if ($configured !== null) {
            return is_file($configured) && is_executable($configured) ? $configured : null;
        }

        return (new ExecutableFinder)->find($tool);
    }

    private function sourceDatabase(): string
    {
        $database = (string) ($this->connection['database'] ?? '');

        if ($database === '') {
            throw new RuntimeException('The PostgreSQL connection has no database name.');
        }

        return $database;
    }

    private function connect(string $database): PDO
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            (string) ($this->connection['host'] ?? '127.0.0.1'),
            (int) ($this->connection['port'] ?? 5432),
            $database,
        );

        $sslmode = (string) ($this->connection['sslmode'] ?? '');
        if ($sslmode !== '') {
            $dsn .= ';sslmode='.$sslmode;
        }

        $pdo = new PDO($dsn, (string) ($this->connection['username'] ?? ''), (string) ($this->connection['password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 15,
        ]);

        return $pdo;
    }

    /**
     * @return list<string>
     */
    private function clientArguments(): array
    {
        return [
            '--host='.(string) ($this->connection['host'] ?? '127.0.0.1'),
            '--port='.(int) ($this->connection['port'] ?? 5432),
            '--username='.(string) ($this->connection['username'] ?? ''),
            '--no-password',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function processEnvironment(): array
    {
        $environment = ['PGPASSWORD' => (string) ($this->connection['password'] ?? '')];

        $sslmode = (string) ($this->connection['sslmode'] ?? '');
        if ($sslmode !== '') {
            $environment['PGSSLMODE'] = $sslmode;
        }

        return $environment;
    }

    /**
     * @param  list<string>  $command
     */
    private function runProcess(array $command, string $tool): void
    {
        $process = new Process($command, null, $this->processEnvironment());
        $process->setTimeout(self::PROCESS_TIMEOUT_SECONDS);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(sprintf(
                '%s exited with code %d: %s',
                $tool,
                (int) $process->getExitCode(),
                trim($process->getErrorOutput()) !== '' ? trim($process->getErrorOutput()) : trim($process->getOutput()),
            ));
        }
    }

    private function serverMajorVersion(PDO $pdo): int
    {
        $versionNum = (int) $pdo->query('SHOW server_version_num')->fetchColumn();

        return intdiv($versionNum, 10000);
    }

    private function clientMajorVersion(string $binary): ?int
    {
        $process = new Process([$binary, '--version']);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        return preg_match('/(\d+)\./', $process->getOutput(), $matches) === 1 ? (int) $matches[1] : null;
    }

    /**
     * @param  list<string>  $tables
     */
    private function countRows(PDO $pdo, array $tables): int
    {
        $total = 0;

        foreach ($tables as $table) {
            $total += (int) $pdo->query('SELECT COUNT(*) FROM '.$this->quoteTable($table))->fetchColumn();
        }

        return $total;
    }

    /**
     * Satır içeriği özeti: her tablo için satırların metin gösteriminin
     * md5'i, sıralanıp birleştirilir; sonra tablo adlarıyla birlikte
     * SHA-256'ya alınır. Fiziksel sıradan bağımsızdır; aynı sunucuda
     * kaynak ile geri yüklenen kopya için aynı içerik aynı özeti verir.
     *
     * @param  list<string>  $tables
     */
    private function contentDigest(PDO $pdo, array $tables): string
    {
        $parts = [];

        foreach ($tables as $table) {
            $sql = 'SELECT COALESCE(string_agg(h, \',\' ORDER BY h), \'\') FROM (SELECT md5(t::text) AS h FROM '.$this->quoteTable($table).' AS t) AS s';
            $parts[] = $table."\0".(string) $pdo->query($sql)->fetchColumn();
        }

        return hash('sha256', implode("\n", $parts));
    }

    /**
     * @return list<string>
     */
    private function publicTables(PDO $pdo): array
    {
        $rows = $pdo->query("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public' ORDER BY tablename")->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_map('strval', $rows));
    }

    private function quoteTable(string $table): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) !== 1) {
            throw new RuntimeException('Refusing to touch an unsafe table name.');
        }

        return '"public".'.$this->quoteIdentifier($table);
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }

    private function dropThrowaway(?PDO $pdo, ?string $throwaway, ?int $serverMajor): void
    {
        if ($pdo === null || $throwaway === null || preg_match(self::THROWAWAY_PATTERN, $throwaway) !== 1) {
            return;
        }

        try {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $force = ($serverMajor ?? 0) >= 13 ? ' WITH (FORCE)' : '';
            $pdo->exec('DROP DATABASE IF EXISTS '.$this->quoteIdentifier($throwaway).$force);
        } catch (Throwable) {
            // Düşürme başarısız olursa geçici veritabanı sunucuda kalır;
            // runbook (docs/124) `zabuno_drill_%` desenini elle temizlemeyi
            // anlatır. Burada gizlemek yerine kayıt zaten "failed" olur.
        }
    }

    private function redact(string $message): string
    {
        $password = (string) ($this->connection['password'] ?? '');

        return $password === '' ? $message : str_replace($password, '[redacted]', $message);
    }

    private function resolveWorkRoot(): string
    {
        if ($this->workRoot !== null) {
            return $this->workRoot;
        }

        return sys_get_temp_dir().DIRECTORY_SEPARATOR.self::DEFAULT_WORK_ROOT_NAME;
    }

    private function cleanupTempChild(?string $tempChild): void
    {
        if ($tempChild === null) {
            return;
        }

        $workRoot = $this->resolveWorkRoot();

        if (! str_starts_with($tempChild, rtrim($workRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            return;
        }

        if (! str_starts_with(basename($tempChild), self::TEMP_CHILD_PREFIX) || ! is_dir($tempChild)) {
            return;
        }

        $archive = $tempChild.DIRECTORY_SEPARATOR.'backup.dump';
        if (is_file($archive)) {
            @unlink($archive);
        }

        @rmdir($tempChild);
    }

    private function elapsedMs(int|float $startedAt): int
    {
        return (int) ((hrtime(true) - $startedAt) / 1_000_000);
    }
}
