<?php

declare(strict_types=1);

namespace App\Infrastructure\Backup;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * DURAN YEDEĞİ ÜRETEN YAZICI — BACKUP-PRODUCE-01 (`docs/107` Faz 1.5).
 *
 * NE YAPAR. Ön kontroller geçerse üretim veritabanının TAMAMINI
 * `pg_dump --format=custom` ile GEÇİCİ bir dosyaya döker, arşivi
 * `pg_restore --list` ile OKUYARAK doğrular, SHA-256 özetini hesaplar,
 * özeti arşivin yanındaki `.sha256` kardeş dosyasına yazar ve geçici
 * dosyayı tek bir `rename()` ile final adına taşır.
 *
 * ÜRETİM VERİSİNE TEK BİR YAZMA YOKTUR. Doğrulama `pg_restore --list`
 * iledir: arşivin içindekiler listelenir, hiçbir şey geri YÜKLENMEZ. Bu
 * sınıf `pg_restore`'u canlı veritabanına hiç çalıştırmaz.
 *
 * HİÇBİR DOSYA SİLİNMEZ. Retention bu paketin dışındadır ve bu bilinçli:
 * silme KURALI yazılmadan önce silme YETENEĞİ de olmamalı. Temizlenen tek
 * şey bu koşunun kendi ürettiği geçici dosyalardır; final ad zaten doluysa
 * üzerine yazmak yerine koşu başarısız olur.
 *
 * SIRA ÖNEMLİ. Hedef, boş alan ve istemci kontrollerinin hepsi döküm
 * BAŞLAMADAN biter. Sırası bozulursa üretim veritabanı gigabaytlarca
 * okunur ve sonuç hiçbir yere yazılamaz: canlı sunucuya bedeli ödetilmiş,
 * karşılığında hiçbir yedek alınmamış olur.
 *
 * PAROLA YALNIZ SÜREÇ ORTAMINDA. `PGPASSWORD` yalnız alt sürecin
 * ortamına konur; argümanlarda (`--no-password`) ve günlükte hiç geçmez.
 * Hata metinleri de parolayı maskeleyerek döner — bir yedekleme arızası,
 * veritabanı parolasını günlüğe düşüren yer olmamalı.
 */
final class PgDumpDatabaseBackupWriter
{
    private const PROCESS_TIMEOUT_SECONDS = 3600;

    private const VERSION_TIMEOUT_SECONDS = 30;

    /** Tamamlanmış bir arşivin adı; yarım kalan bir döküm bu adı ALMAZ. */
    private const ARCHIVE_SUFFIX = '.dump';

    /** Yarım dökümün taşıdığı ad — `*.dump` aramasına takılmaz. */
    private const PARTIAL_SUFFIX = '.dump.part';

    /**
     * @param  array<string, mixed>  $connection  driver, host, port, database, username, password, sslmode
     */
    public function __construct(
        private readonly array $connection,
        private readonly string $destination,
        private readonly int $minimumFreeBytes,
        private readonly int $minimumClientMajor,
        private readonly ?string $pgDumpBinary = null,
        private readonly ?string $pgRestoreBinary = null,
    ) {}

    /**
     * @return array{ok: bool, reason: string, message: string, path: string|null, bytes: int|null, sha256: string|null}
     */
    public function write(): array
    {
        $driver = (string) ($this->connection['driver'] ?? '');

        if ($driver !== 'pgsql') {
            return $this->refused(
                'not_applicable',
                "The default database connection uses the `{$driver}` driver; production runs PostgreSQL and an archive "
                .'produced here could not be restored onto it. Nothing was backed up.',
            );
        }

        $database = (string) ($this->connection['database'] ?? '');

        if ($database === '') {
            return $this->refused('not_applicable', 'The PostgreSQL connection has no database name; nothing was backed up.');
        }

        $destination = rtrim($this->destination, DIRECTORY_SEPARATOR);

        if ($destination === '' || ! is_dir($destination) || ! is_writable($destination)) {
            return $this->refused(
                'destination_unusable',
                "The backup destination `{$this->destination}` is not a writable directory; the dump was not started.",
            );
        }

        $free = disk_free_space($destination);

        if ($free === false || (int) $free < $this->minimumFreeBytes) {
            return $this->refused(
                'insufficient_disk',
                sprintf(
                    'The backup destination has %s free and at least %d bytes are required; the dump was not started so a '
                    .'half-written file can never fill the disk the database itself writes to.',
                    $free === false ? 'an unreadable amount of space' : (int) $free.' bytes',
                    $this->minimumFreeBytes,
                ),
            );
        }

        $pgDump = $this->locate('pg_dump', $this->pgDumpBinary);

        if ($pgDump === null) {
            return $this->refused('client_missing', 'pg_dump was not found; no archive was produced.');
        }

        $dumpMajor = $this->clientMajorVersion($pgDump);

        if ($dumpMajor === null || $dumpMajor < $this->minimumClientMajor) {
            return $this->refused(
                'client_too_old',
                sprintf(
                    'pg_dump reports major version %s but the production server needs at least %d; an archive written by an '
                    .'older client cannot be restored, so none was written.',
                    $dumpMajor === null ? 'an unreadable version' : (string) $dumpMajor,
                    $this->minimumClientMajor,
                ),
            );
        }

        // Doğrulama aracı da ÖNCEDEN aranır: okunamayacak bir arşiv
        // üretip "yedek alındı" demektense hiç üretmemek doğrudur.
        $pgRestore = $this->locate('pg_restore', $this->pgRestoreBinary);

        if ($pgRestore === null) {
            return $this->refused('client_missing', 'pg_restore was not found, so the archive could not be verified; none was produced.');
        }

        $restoreMajor = $this->clientMajorVersion($pgRestore);

        if ($restoreMajor === null || $restoreMajor < $this->minimumClientMajor) {
            return $this->refused(
                'client_too_old',
                sprintf(
                    'pg_restore reports major version %s but at least %d is required to read the archive; none was produced.',
                    $restoreMajor === null ? 'an unreadable version' : (string) $restoreMajor,
                    $this->minimumClientMajor,
                ),
            );
        }

        $stem = $destination.DIRECTORY_SEPARATOR.'zabuno-'.gmdate('Y-m-d-His').'-'.bin2hex(random_bytes(4));
        $archive = $stem.self::ARCHIVE_SUFFIX;
        $partial = $stem.self::PARTIAL_SUFFIX;

        // Üzerine YAZMAK yok: duran bir arşiv, yeni bir koşunun adı ona
        // denk geldi diye kaybolmaz.
        if (file_exists($archive) || file_exists($partial)) {
            return $this->refused('name_taken', 'A file already occupies the archive name for this run; nothing was overwritten.');
        }

        try {
            $dumped = $this->run([
                $pgDump,
                '--format=custom',
                '--no-owner',
                '--no-privileges',
                '--file='.$partial,
                ...$this->clientArguments(),
                '--dbname='.$database,
            ], 'pg_dump');

            if ($dumped !== null) {
                return $this->failed('dump_failed', $dumped, $partial);
            }

            clearstatcache();
            $bytes = is_file($partial) ? filesize($partial) : false;

            if ($bytes === false || $bytes === 0) {
                return $this->failed('dump_failed', 'pg_dump produced an empty archive.', $partial);
            }

            // SALT OKUMA. Arşivin içindekiler listelenir; hiçbir şey geri
            // yüklenmez ve kaynak veritabanına dokunulmaz.
            $listed = $this->run([$pgRestore, '--list', $partial], 'pg_restore --list');

            if ($listed !== null) {
                return $this->failed('unreadable_archive', $listed, $partial);
            }

            $digest = hash_file('sha256', $partial);

            if ($digest === false) {
                return $this->failed('unreadable_archive', 'The archive could not be read back to compute its checksum.', $partial);
            }

            /*
                ÖZET İKİ YERDE YAŞAR. Geri yükleme günü sorulacak soru
                "dosya duruyor mu" değil, "dosya BOZULMADAN duruyor mu"dur.
                Kardeş dosya arşivle birlikte taşınır; koşunun raporu ise
                izlemeye akar. İkisi ayrışırsa kanıtın kendisi şüphelidir.
            */
            $partialSidecar = $partial.'.sha256';

            if (file_put_contents($partialSidecar, $digest.'  '.basename($archive)."\n") === false) {
                return $this->failed('sidecar_failed', 'The checksum sidecar could not be written.', $partial, $partialSidecar);
            }

            // Final ad, TEK bir atomik adımda ve yalnız döküm bittikten
            // sonra verilir: yarım bir döküm asla `*.dump` diye durmaz.
            if (! @rename($partial, $archive)) {
                return $this->failed('rename_failed', 'The completed dump could not be moved to its final name.', $partial, $partialSidecar);
            }

            /*
                KARDEŞ DOSYA OLMADAN "BAŞARILI" DENMEZ.

                Arşiv bu noktada final adıyla duruyor ve SİLİNMEZ — duran
                bir yedeği silmek bu paketin engellediği tek gerçek felaket
                senaryosudur. Ama özeti yanında olmayan bir arşivin
                bütünlüğü taşındıktan sonra bir daha doğrulanamaz; koşu
                bunu sessizce yutmak yerine açıkça başarısız döner ve
                operatöre hangi dosyanın özetsiz kaldığını söyler.
            */
            if (! @rename($partialSidecar, $archive.'.sha256')) {
                $this->discardOwnTemporaryFile($partialSidecar);

                return $this->refused(
                    'sidecar_failed',
                    sprintf(
                        'The archive was written to %s but its `.sha256` sibling could not be put in place, so its integrity '
                        .'could not be made verifiable. The archive was left untouched; record its checksum by hand (%s) '
                        .'before relying on it.',
                        $archive,
                        $digest,
                    ),
                );
            }

            return [
                'ok' => true,
                'reason' => 'written',
                'message' => sprintf('Database archive written: %s (%d bytes, sha256 %s).', $archive, $bytes, $digest),
                'path' => $archive,
                'bytes' => $bytes,
                'sha256' => $digest,
            ];
        } catch (Throwable $e) {
            return $this->failed('dump_failed', $this->redact($e->getMessage()), $partial, $partial.'.sha256');
        }
    }

    /**
     * Alt süreci çalıştırır; başarılıysa `null`, değilse maskelenmiş bir
     * hata metni döner.
     *
     * @param  list<string>  $command
     */
    private function run(array $command, string $tool): ?string
    {
        $process = new Process($command, null, $this->processEnvironment());
        $process->setTimeout(self::PROCESS_TIMEOUT_SECONDS);
        $process->run();

        if ($process->isSuccessful()) {
            return null;
        }

        $detail = trim($process->getErrorOutput()) !== '' ? trim($process->getErrorOutput()) : trim($process->getOutput());

        return $this->redact(sprintf('%s exited with code %d: %s', $tool, (int) $process->getExitCode(), $detail));
    }

    private function locate(string $tool, ?string $configured): ?string
    {
        if ($configured !== null && $configured !== '') {
            return is_file($configured) && is_executable($configured) ? $configured : null;
        }

        return (new ExecutableFinder)->find($tool);
    }

    private function clientMajorVersion(string $binary): ?int
    {
        $process = new Process([$binary, '--version']);
        $process->setTimeout(self::VERSION_TIMEOUT_SECONDS);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        return preg_match('/(\d+)\./', $process->getOutput(), $matches) === 1 ? (int) $matches[1] : null;
    }

    /**
     * Parola BURADA YOK: `--no-password` ile istemci sormaz, değer yalnız
     * süreç ortamından geçer.
     *
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

    private function redact(string $message): string
    {
        $password = (string) ($this->connection['password'] ?? '');

        return $password === '' ? $message : str_replace($password, '[redacted]', $message);
    }

    /**
     * @return array{ok: bool, reason: string, message: string, path: null, bytes: null, sha256: null}
     */
    private function refused(string $reason, string $message): array
    {
        return ['ok' => false, 'reason' => $reason, 'message' => $message, 'path' => null, 'bytes' => null, 'sha256' => null];
    }

    /**
     * @return array{ok: bool, reason: string, message: string, path: null, bytes: null, sha256: null}
     */
    private function failed(string $reason, string $message, string ...$temporaries): array
    {
        foreach ($temporaries as $temporary) {
            $this->discardOwnTemporaryFile($temporary);
        }

        return $this->refused($reason, $message);
    }

    /**
     * YALNIZ BU KOŞUNUN KENDİ geçici dosyası silinir — adı bu sınıfın
     * ürettiği desene uymayan hiçbir şeye dokunulmaz.
     */
    private function discardOwnTemporaryFile(string $path): void
    {
        $name = basename($path);

        if (! str_starts_with($name, 'zabuno-')) {
            return;
        }

        if (! str_ends_with($name, self::PARTIAL_SUFFIX) && ! str_ends_with($name, self::PARTIAL_SUFFIX.'.sha256')) {
            return;
        }

        if (is_file($path)) {
            @unlink($path);
        }
    }
}
