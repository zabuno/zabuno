<?php

declare(strict_types=1);

return [
    'scanner' => [
        'driver' => env('MEDIA_SCANNER_DRIVER', 'unavailable'),

        'clamav' => [
            'binary_path' => env('MEDIA_SCANNER_CLAMAV_BINARY_PATH', ''),
            'timeout_seconds' => (float) env('MEDIA_SCANNER_CLAMAV_TIMEOUT_SECONDS', 10.0),
        ],
    ],
];
