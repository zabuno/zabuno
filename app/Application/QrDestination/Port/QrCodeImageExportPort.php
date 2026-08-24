<?php

declare(strict_types=1);

namespace App\Application\QrDestination\Port;

use App\Application\QrDestination\Dto\QrRenderedImage;
use App\Domain\QrDestination\QrLayout;
use RuntimeException;

interface QrCodeImageExportPort
{
    /**
     * @throws RuntimeException
     */
    public function renderPng(string $data, ?QrLayout $layout = null): QrRenderedImage;

    /**
     * @throws RuntimeException
     */
    public function renderSvg(string $data, ?QrLayout $layout = null): QrRenderedImage;
}
