<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Security\UseCase\RecordBackupRestoreEvidence;
use Illuminate\Console\Command;
use Symfony\Component\Console\Exception\ExceptionInterface as SymfonyConsoleExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class RecordBackupRestoreEvidenceCommand extends Command
{
    protected $signature = 'security:evidence:backup-restore';

    protected $description = 'Run the local SQLite backup/restore drill against the frozen table manifest and append one evidence record.';

    /**
     * No options are defined on this command's signature. A caller
     * supplying an unrecognized option (e.g. --status) must fail the
     * command rather than let the outcome be influenced by input.
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

    public function handle(RecordBackupRestoreEvidence $useCase): int
    {
        $record = $useCase->execute();

        if ($record->status() === 'passed') {
            $this->info('Backup/restore evidence recorded: passed.');

            return self::SUCCESS;
        }

        $this->error('Backup/restore evidence recorded: failed.');

        return self::FAILURE;
    }
}
