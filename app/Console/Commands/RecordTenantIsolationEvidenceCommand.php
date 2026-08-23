<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Security\UseCase\RecordTenantIsolationEvidence;
use Illuminate\Console\Command;
use Symfony\Component\Console\Exception\ExceptionInterface as SymfonyConsoleExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class RecordTenantIsolationEvidenceCommand extends Command
{
    protected $signature = 'security:evidence:tenant-isolation';

    protected $description = 'Run the frozen tenant-isolation suite and append one evidence record.';

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

    public function handle(RecordTenantIsolationEvidence $useCase): int
    {
        $record = $useCase->execute();

        if ($record->status() === 'passed') {
            $this->info('Tenant-isolation evidence recorded: passed.');

            return self::SUCCESS;
        }

        $this->error('Tenant-isolation evidence recorded: failed.');

        return self::FAILURE;
    }
}
