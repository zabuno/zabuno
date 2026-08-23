<?php

declare(strict_types=1);

namespace App\Domain\Team;

enum InvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Cancelled = 'cancelled';
}
