<?php

declare(strict_types=1);

namespace App\Domain\Content;

/**
 * Sayfanın hangi ortamda sunulduğu — FF-117.
 *
 * Ayrı bir tür, çünkü karar ortama göre ZITTIR: production'da yayınlanmamış bir
 * sayfa 404 döner ve indekslenmez; staging'de aynı sayfa 200 döner ve ekip onu
 * gezerek kontrol eder. İkisini tek bir `bool` ile ifade etmek, bir gün
 * production'da taslak yayımlamak demekti.
 */
enum PageEnvironment: string
{
    case Production = 'production';
    case Staging = 'staging';
}
