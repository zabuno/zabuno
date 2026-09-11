<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Backup\PgDumpDatabaseBackupWriter;
use Illuminate\Console\Command;

/**
 * DURAN YEDEĞİ ÜRETEN KOMUT — BACKUP-PRODUCE-01 (`docs/107` Faz 1.5).
 *
 * "Tatbikat edilen yedek" ile "duran yedek" aynı şey değildir. Günlük
 * tatbikat (`security:evidence:backup-restore`, `docs/124`) "geri
 * gelebiliyor mu" sorusunu sorar ve kendi dökümünü işi bitince siler. Bu
 * komut öbür soruyu cevaplar: "bugün sunucu gitse, geri dönülecek bir
 * dosya VAR MI?"
 *
 * SAHİBİN YOLCULUĞU. Kadıköy'deki restoranın sahibi menüsünü,
 * fotoğraflarını ve altı aylık siparişini bu sunucuya emanet etti. Yeşil
 * yanan tatbikat kaydı ona "veriniz güvende" diyordu; oysa geri yüklenecek
 * bir arşiv yoktu. Bu komut her gece o arşivi kalıcı hacme bırakır.
 *
 * "GEÇTİ" DEMEZ. PostgreSQL dışında bir sürücüde, yazılamayan bir hedefte,
 * dolan bir diskte ya da eksik/eski bir `pg_dump` ile komut SIFIRDAN FARKLI
 * çıkar ve sebebini söyler. Uygulanamayan bir koşu "geçti" değildir;
 * yedeği olduğunu sanmak, olmadığını bilmekten pahalıdır.
 *
 * `--json` operatörün izlemeye akıtabileceği makine okunur raporu basar:
 * `path`, `bytes`, `sha256`. Aynı özet arşivin yanındaki `.sha256` kardeş
 * dosyasında da durur.
 */
final class BackupDatabaseCommand extends Command
{
    protected $signature = 'zabuno:backup:database {--json : Print the run report as JSON}';

    protected $description = 'Write a full custom-format PostgreSQL archive of the default connection into the durable backup directory, verify it by listing it, and record its SHA-256 beside it. Never deletes an existing file.';

    public function handle(): int
    {
        $connection = (string) config('database.default');

        $writer = new PgDumpDatabaseBackupWriter(
            connection: (array) config('database.connections.'.$connection, []),
            destination: (string) config('backup.database.path'),
            minimumFreeBytes: (int) config('backup.database.minimum_free_bytes'),
            minimumClientMajor: (int) config('backup.database.minimum_client_major'),
            pgDumpBinary: config('backup.database.pg_dump_binary'),
            pgRestoreBinary: config('backup.database.pg_restore_binary'),
        );

        $result = $writer->write();

        if ((bool) $this->option('json')) {
            // YALNIZ JSON. Rapor bir boruya akar; araya düşen tek bir
            // insan cümlesi onu ayrıştırılamaz hâle getirir.
            $this->output->writeln((string) json_encode([
                'ok' => $result['ok'],
                'reason' => $result['reason'],
                'message' => $result['message'],
                'path' => $result['path'],
                'bytes' => $result['bytes'],
                'sha256' => $result['sha256'],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return $result['ok'] ? self::SUCCESS : self::FAILURE;
        }

        if ($result['ok']) {
            $this->info($result['message']);

            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }
}
