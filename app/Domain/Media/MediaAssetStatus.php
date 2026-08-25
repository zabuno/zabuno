<?php

declare(strict_types=1);

namespace App\Domain\Media;

enum MediaAssetStatus: string
{
    case Quarantined = 'quarantined';
    case Rejected = 'rejected';
    case Scanning = 'scanning';
    case Accepted = 'accepted';
}
