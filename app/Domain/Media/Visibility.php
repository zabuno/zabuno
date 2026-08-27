<?php

declare(strict_types=1);

namespace App\Domain\Media;

/**
 * Kimin görebileceği.
 *
 * Orijinal dosya çoğu zaman public olmamalıdır; yayınlanan menü yalnız
 * temizlenmiş ve optimize edilmiş rendition'ları kullanır (`docs/49` §6).
 */
enum Visibility: string
{
    case Private = 'private';
    case Tenant = 'tenant';
    case Public = 'public';
    case Signed = 'signed';
}
