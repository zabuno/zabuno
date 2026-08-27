<?php

declare(strict_types=1);

namespace App\Domain\Media;

/**
 * Varlığın KULLANICI için hangi hayat evresinde olduğu.
 *
 * `PURGED` ayrı bir durumdur ve satırın silinmesiyle aynı şey değildir:
 * denetim kaydı, kotanın yeniden hesabı ve "bu neydi" sorusu için kayıt
 * kalır, dosyanın kendisi gider.
 */
enum LifecycleStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
    case Trashed = 'trashed';
    case Purged = 'purged';

    public function isUsable(): bool
    {
        return $this === self::Active || $this === self::Draft;
    }
}
