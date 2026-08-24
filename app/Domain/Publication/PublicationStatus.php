<?php

declare(strict_types=1);

namespace App\Domain\Publication;

enum PublicationStatus: string
{
    case Pending = 'pending';
    case Generating = 'generating';
    case Published = 'published';
    case Failed = 'failed';
    case Superseded = 'superseded';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => $target === self::Generating,
            self::Generating => in_array($target, [self::Published, self::Failed], true),
            self::Published => $target === self::Superseded,
            self::Failed, self::Superseded => false,
        };
    }
}
