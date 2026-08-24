<?php

declare(strict_types=1);

namespace App\Domain\Platform;

enum PlatformRole: string
{
    case SuperAdmin = 'super_admin';
}
