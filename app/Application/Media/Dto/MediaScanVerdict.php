<?php

declare(strict_types=1);

namespace App\Application\Media\Dto;

enum MediaScanVerdict: string
{
    case Clean = 'clean';
    case Infected = 'infected';
    case Indeterminate = 'indeterminate';
}
