<?php

declare(strict_types=1);

namespace App\Application\Security\Port;

interface TenantIsolationSuiteRunnerPort
{
    /**
     * @param  list<string>  $paths
     * @return array{passed: bool, exit_code: int, duration_ms: int, output: string}
     */
    public function run(array $paths): array;
}
