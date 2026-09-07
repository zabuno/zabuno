<?php

declare(strict_types=1);

namespace App\Domain\Security;

/**
 * Hangi koşucu koştu — kanıt kaydının kimliği buradan türer.
 *
 * Tek koşucu varken (SQLite) kapsam, koşucu adı ve iddia metni sabitti.
 * Üretim hedefi PostgreSQL olunca aynı kayıt iki farklı gerçeği taşımak
 * zorunda kaldı ve "hangisi" sorusu kaydın içinde cevaplanmalıydı: bir
 * geliştirici makinesindeki SQLite tatbikatı, sunucudaki pg_dump
 * tatbikatının yerine sunulamaz (docs/124).
 */
enum BackupRestoreDriver: string
{
    case Sqlite = 'sqlite';
    case Pgsql = 'pgsql';

    public function scope(): string
    {
        return match ($this) {
            self::Sqlite => 'local_sqlite_online_backup_restore_drill',
            self::Pgsql => 'postgres_pg_dump_isolated_database_restore_drill',
        };
    }

    public function runner(): string
    {
        return match ($this) {
            self::Sqlite => 'sqlite3_online_backup',
            self::Pgsql => 'pg_dump_custom_pg_restore',
        };
    }

    public function claim(): string
    {
        return match ($this) {
            self::Sqlite => 'This evidence reflects one local SQLite online-backup and isolated file-copy restore drill against a frozen table manifest. It is not an RPO/RTO proof, not a production DR drill, and does not test cross-host or point-in-time recovery.',
            self::Pgsql => 'This evidence reflects one pg_dump (custom format, frozen table manifest only) and one pg_restore into a throwaway database on the same PostgreSQL server, restoring the pre-data and data sections only; row counts and content digests were compared, then the throwaway database was dropped. It is not an RPO/RTO proof, not a production DR drill on the deployment host, and does not test cross-host or point-in-time recovery.',
        };
    }
}
