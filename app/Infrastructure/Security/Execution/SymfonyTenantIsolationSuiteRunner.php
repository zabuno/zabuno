<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Execution;

use App\Application\Security\Port\TenantIsolationSuiteRunnerPort;
use App\Domain\Security\TenantIsolationSuiteManifest;
use Symfony\Component\Process\Process;

final class SymfonyTenantIsolationSuiteRunner implements TenantIsolationSuiteRunnerPort
{
    private const TIMEOUT_SECONDS = 300;

    /**
     * @param  list<string>  $paths
     * @return array{passed: bool, exit_code: int, duration_ms: int, output: string}
     */
    public function run(array $paths): array
    {
        if ($paths !== TenantIsolationSuiteManifest::paths()) {
            throw new \InvalidArgumentException('TenantIsolationSuiteRunnerPort::run() only accepts the frozen suite manifest paths.');
        }

        $process = new Process([
            PHP_BINARY,
            base_path('artisan'),
            'test',
            ...$paths,
        ], base_path());

        $process->setTimeout(self::TIMEOUT_SECONDS);

        $startedAt = hrtime(true);
        $process->run();
        $durationMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

        $output = $process->getOutput().$process->getErrorOutput();

        return [
            'passed' => $process->isSuccessful(),
            'exit_code' => (int) $process->getExitCode(),
            'duration_ms' => $durationMs,
            'output' => $output,
        ];
    }
}
