<?php

declare(strict_types=1);

namespace App\Application\DataRights\Exception;

use RuntimeException;

/**
 * Silme BAŞLAMADI ve sebebi adıyla söylenir — FF-226.
 *
 * Sebep serbest bir cümle değil bir ADdır (`legal_hold`), çünkü ekran onu
 * kendi dilinde anlatmalı; sunucunun cümlesini olduğu gibi göstermek,
 * çevrilemez bir metni arayüze sızdırmak olurdu.
 */
final class ErasureBlockedException extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $count = 0)
    {
        parent::__construct($reason);
    }
}
