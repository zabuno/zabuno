<?php

declare(strict_types=1);

namespace App\Application\QrDestination\Dto;

final class BulkQrCreationResult
{
    /**
     * @param  list<DiningAreaRecord>  $areas
     * @param  list<DiningTableRecord>  $tables
     * @param  list<QrCodeRecord>  $qrCodes
     */
    public function __construct(
        public readonly array $areas,
        public readonly array $tables,
        public readonly array $qrCodes,
    ) {}
}
