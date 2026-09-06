<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use Tests\TestCase;

/**
 * FF-201 — taahhüt ve destek adresi KONTEYNERE GEÇMELİ (`docs/93` FF-36
 * dersi).
 *
 * Sahip sunucunun `.env`'ine `SUPPORT_RESPONSE_COMMITMENT_HOURS=24` yazsa
 * bile `docker-compose.yml` o değeri konteynere aktarmıyorsa uygulama onu
 * hiç görmez ve hiçbir sayfa taahhüt göstermez — sahip "yazdım ama
 * çıkmıyor" der ve arıza kodda değil aktarımda olur.
 *
 * Requirement IDs: SUPPORT-DEPLOY-ENV-01, SUPPORT-ENV-EXAMPLE-01.
 */
final class SupportDeploymentContractTest extends TestCase
{
    private const VARIABLES = ['SUPPORT_RESPONSE_COMMITMENT_HOURS', 'SUPPORT_EMAIL'];

    public function test_the_support_settings_reach_the_container_as_env_references(): void
    {
        $compose = (string) file_get_contents(base_path('docker-compose.yml'));

        foreach (self::VARIABLES as $variable) {
            self::assertMatchesRegularExpression(
                '/^\s*'.$variable.':\s*\$\{'.$variable.'\b/m',
                $compose,
                "SUPPORT-DEPLOY-ENV-01: `{$variable}` konteynere `\${...}` başvurusu olarak aktarılmıyor."
            );
        }
    }

    public function test_the_example_env_files_name_the_variables_without_a_value(): void
    {
        foreach (['.env.example', '.env.production.example'] as $file) {
            $contents = (string) file_get_contents(base_path($file));

            foreach (self::VARIABLES as $variable) {
                // Ad VAR, değer YOK: sayı sahibin kararıdır, örnek dosyanın değil.
                self::assertMatchesRegularExpression(
                    '/^'.$variable.'=[ \t]*$/m',
                    $contents,
                    "SUPPORT-ENV-EXAMPLE-01: [{$file}] `{$variable}` satırı boş değerle bulunmalı."
                );
            }
        }
    }
}
