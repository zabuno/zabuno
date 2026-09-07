<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Security\UseCase\RecordBackupRestoreEvidence;
use App\Application\Security\UseCase\RecordMediaBackupRestoreEvidence;
use App\Domain\Security\BackupRestoreEvidenceRecord;
use App\Domain\Security\MediaBackupRestoreEvidenceRecord;
use Illuminate\Console\Command;
use Symfony\Component\Console\Exception\ExceptionInterface as SymfonyConsoleExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Yedek/geri yükleme TATBİKATI — "denenmemiş bir yedek, yedek değildir"
 * (docs/107 Faz 1.5, docs/124).
 *
 * Varsayılan koşu iki tatbikattır: veritabanı (bağlantıya göre SQLite ya
 * da PostgreSQL koşucusu) ve medya (`storage/app` medya kökü). Her biri
 * kendi kanıt tablosuna bir satır ekler; komut yalnız ikisi de geçtiyse
 * sıfırla çıkar. `--database` / `--media` koşuyu tek tatbikata daraltır;
 * `--json` eklenen kayıtları olduğu gibi basar.
 */
final class RecordBackupRestoreEvidenceCommand extends Command
{
    protected $signature = 'security:evidence:backup-restore
        {--database : Run only the database drill (default: database and media)}
        {--media : Run only the media drill (default: database and media)}
        {--json : Print the appended evidence records as JSON}';

    protected $description = 'Run the backup/restore drills (database via the connection\'s runner, media root via tar), append one evidence record per drill, and exit 0 only when every selected drill passed.';

    /**
     * Only the three declared options exist. A caller supplying an
     * unrecognized option (e.g. --status) must fail the command rather
     * than let the outcome be influenced by input.
     */
    #[\Override]
    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::run($input, $output);
        } catch (SymfonyConsoleExceptionInterface $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');

            return self::FAILURE;
        }
    }

    public function handle(RecordBackupRestoreEvidence $database, RecordMediaBackupRestoreEvidence $media): int
    {
        $onlyDatabase = (bool) $this->option('database');
        $onlyMedia = (bool) $this->option('media');

        $runDatabase = ! $onlyMedia || $onlyDatabase;
        $runMedia = ! $onlyDatabase || $onlyMedia;

        $databaseRecord = $runDatabase ? $database->execute() : null;
        $mediaRecord = $runMedia ? $media->execute() : null;

        $passed = ($databaseRecord === null || $databaseRecord->status() === 'passed')
            && ($mediaRecord === null || $mediaRecord->status() === 'passed');

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'passed' => $passed,
                'database' => $databaseRecord?->toArray(),
                'media' => $mediaRecord?->toArray(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return $passed ? self::SUCCESS : self::FAILURE;
        }

        if ($databaseRecord !== null) {
            $this->report('Backup/restore evidence recorded ('.$databaseRecord->driver().')', $databaseRecord);
        }

        if ($mediaRecord !== null) {
            $this->report('Media backup/restore evidence recorded', $mediaRecord);
        }

        return $passed ? self::SUCCESS : self::FAILURE;
    }

    private function report(string $label, BackupRestoreEvidenceRecord|MediaBackupRestoreEvidenceRecord $record): void
    {
        $line = $label.': '.$record->status().'.';

        match ($record->status()) {
            'passed' => $this->info($line),
            'unknown' => $this->warn($line.' The drill could not be attempted; see exit code '.$record->exitCode().'.'),
            default => $this->error($line),
        };
    }
}
