<?php

declare(strict_types=1);

namespace App\Application\Media\Dto;

enum MediaProcessingOutcome
{
    case Succeeded;
    case Failed;
    case Indeterminate;
}
