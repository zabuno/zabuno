<?php

declare(strict_types=1);

namespace App\Application\QrDestination\Port;

use App\Application\QrDestination\Dto\QrPrintCard;
use App\Application\QrDestination\Dto\QrRenderedImage;
use RuntimeException;

/**
 * Basılabilir sayfa — `docs/104` Döngü 8.
 *
 * Tek kodun PDF'inden (`QrCodePdfExportPort`) AYRI bir port: o, tek bir kodun
 * kendi kâğıdıdır (duvara asılacak afiş); bu, kesilip masalara dağıtılacak bir
 * kart destesidir. İkisini tek imzada birleştirmek, kâğıt boyu ve yön gibi
 * ayarları anlamsızca deste üretimine de taşırdı.
 */
interface QrPrintSheetPort
{
    /**
     * @param  list<QrPrintCard>  $cards
     *
     * @throws RuntimeException
     */
    public function renderSheet(array $cards, string $caption, string $brandName): QrRenderedImage;
}
