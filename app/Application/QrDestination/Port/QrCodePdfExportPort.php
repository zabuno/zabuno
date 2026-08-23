<?php

declare(strict_types=1);

namespace App\Application\QrDestination\Port;

use App\Application\QrDestination\Dto\QrRenderedImage;
use App\Domain\QrDestination\QrLayout;
use RuntimeException;

interface QrCodePdfExportPort
{
    /**
     * @throws RuntimeException
     */
    public function renderPdf(string $pngBytes, string $token, ?string $paperSize = null, ?string $orientation = null, ?QrLayout $layout = null): QrRenderedImage;
}
