<?php

declare(strict_types=1);

namespace App\Application\QrDestination\Port;

use App\Application\QrDestination\Dto\QrRenderedImage;
use RuntimeException;

/**
 * Basılacak kartı PDF'e çevirir — FF-120.
 *
 * Girdi, `QrCardSvg`'nin ürettiği SVG'nin TA KENDİSİDİR. İkinci bir besteci
 * yazmak, ekrandaki önizlemenin bir şey, yazıcıdan çıkan kartın başka bir şey
 * olması demekti.
 */
interface QrCardExportPort
{
    /**
     * @param  float  $widthMm  Kartın gerçek fiziksel genişliği.
     *
     * @throws RuntimeException
     */
    public function renderCardPdf(string $cardSvg, float $widthMm, float $heightMm): QrRenderedImage;
}
