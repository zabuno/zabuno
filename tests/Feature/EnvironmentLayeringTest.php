<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Deliberately does NOT boot the Laravel container or mutate runtime env
 * (no putenv/$_ENV): PHPUnit's own process may already run under
 * APP_ENV=testing (phpunit.xml), so asserting against config('app.env')
 * after putenv('APP_ENV') would assert on the test runner's own
 * environment/config-cache state, not on the product's env-layering
 * contract — that assertion could stay red in a correct implementation too
 * (MASTER finding). Instead this reads the static config/environments.php
 * mapping (data-only, mirrors config/core-modules.php) against the
 * .env.*.example file contents directly — a deterministic file contract,
 * independent of container boot order. Encodes docs/26 S1-WP01 "env/config
 * katmanlama (dev/staging/prod)".
 */
final class EnvironmentLayeringTest extends TestCase
{
    private const REPO_ROOT = __DIR__.'/../..';

    private const MAPPING_PATH = self::REPO_ROOT.'/config/environments.php';

    public function test_environment_mapping_declares_exactly_local_staging_and_production(): void
    {
        $mapping = $this->loadMapping();

        self::assertSame(
            ['local', 'staging', 'production'],
            array_keys($mapping),
            'config/environments.php tam olarak local/staging/production katmanlarını bildirmeli.'
        );
    }

    public function test_each_mapped_environment_file_exists_and_declares_its_own_app_env(): void
    {
        $mapping = $this->loadMapping();

        foreach ($mapping as $environment => $definition) {
            $path = $this->examplePath($definition);
            self::assertFileExists($path, "{$environment} için env dosyası eksik: {$definition['file']}");

            $contents = (string) file_get_contents($path);
            self::assertStringContainsString(
                "APP_ENV={$definition['app_env']}",
                $contents,
                "{$definition['file']} APP_ENV={$definition['app_env']} bildirmiyor."
            );
        }
    }

    public function test_staging_and_production_disable_debug_mode_by_contract(): void
    {
        $mapping = $this->loadMapping();

        foreach (['staging', 'production'] as $environment) {
            self::assertArrayHasKey($environment, $mapping, "config/environments.php {$environment} tanımı eksik.");

            $path = $this->examplePath($mapping[$environment]);
            $contents = (string) file_get_contents($path);

            self::assertStringContainsString(
                'APP_DEBUG=false',
                $contents,
                "{$mapping[$environment]['file']} APP_DEBUG=false bildirmiyor."
            );
        }
    }

    /**
     * @return array<string, array{file: string, app_env: string}>
     */
    private function loadMapping(): array
    {
        self::assertFileExists(self::MAPPING_PATH, 'config/environments.php henüz kurulmadı.');

        /** @var mixed $mapping */
        $mapping = require self::MAPPING_PATH;
        self::assertIsArray($mapping, 'config/environments.php bir array döndürmeli.');

        return $mapping;
    }

    /**
     * @param  array{file: string, app_env: string}  $definition
     */
    private function examplePath(array $definition): string
    {
        return self::REPO_ROOT.'/'.$definition['file'];
    }
}
