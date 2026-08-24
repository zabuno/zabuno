<?php

declare(strict_types=1);

namespace App\Domain\MenuCatalog;

enum MenuState
{
    case Draft;
    case Published;
}
