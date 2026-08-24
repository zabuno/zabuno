<?php

declare(strict_types=1);

namespace App\Domain\QrDestination;

enum QrCodeState: string
{
    case Active = 'active';
    case Disabled = 'disabled';

    public function canTransitionTo(self $target): bool
    {
        return $this === self::Active && $target === self::Disabled;
    }
}
