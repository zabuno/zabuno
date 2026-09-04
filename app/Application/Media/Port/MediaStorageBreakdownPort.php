<?php

declare(strict_types=1);

namespace App\Application\Media\Port;

use App\Application\Media\Dto\MediaStorageBreakdown;

/**
 * "Yeri ne dolduruyor?" (`docs/108` §6.4).
 *
 * `MediaQuotaPort`'tan AYRI bir porttur ve ayrılığı bilinçlidir: kota
 * durumu HER YÜKLEMEDE okunur (`admits()`), kırılım ise yalnız sahip
 * ekrana baktığında. İkisini birleştirmek, her yüklemeye bir gruplama
 * sorgusu daha eklerdi.
 */
interface MediaStorageBreakdownPort
{
    public function breakdownFor(int $workspaceId): MediaStorageBreakdown;
}
