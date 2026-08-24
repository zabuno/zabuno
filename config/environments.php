<?php

declare(strict_types=1);

/**
 * Data-only dev/staging/production env layering contract (docs/26 S1-WP01,
 * mirrors config/core-modules.php's plain-array style). Maps each deployment
 * environment to its example env file and expected APP_ENV value; staging
 * and production additionally require APP_DEBUG=false in that file
 * (tests/Feature/EnvironmentLayeringTest.php enforces the same contract at
 * runtime). This file does not select a profile automatically — the actual
 * .env used by a deployment is still an explicit, manual copy of the
 * relevant .env.*.example (no auto-detection is claimed).
 */
return [
    'local' => [
        'file' => '.env.example',
        'app_env' => 'local',
    ],
    'staging' => [
        'file' => '.env.staging.example',
        'app_env' => 'staging',
    ],
    'production' => [
        'file' => '.env.production.example',
        'app_env' => 'production',
    ],
];
