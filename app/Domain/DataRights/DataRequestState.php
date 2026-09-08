<?php

declare(strict_types=1);

namespace App\Domain\DataRights;

/**
 * Talebin hâlleri — FF-226 (`docs/138`).
 *
 * İki türün hâlleri AYRIDIR ve karışmaz. Dışa aktarma bir İŞTİR: sıraya
 * girer, koşar, biter ya da düşer. Silme bir SÖZDÜR: bir gün için
 * planlanır, o güne kadar geri alınabilir, sonra yürür.
 *
 * `Expired` ayrı bir hâldir, `Ready`'nin bir alt durumu değil: dosyanın
 * süresi dolduğunda ekran "hazır ama indirilemiyor" değil, "süresi doldu,
 * yeniden isteyin" demeli.
 */
enum DataRequestState: string
{
    // Dışa aktarma.
    case Queued = 'queued';
    case Running = 'running';
    case Ready = 'ready';
    case Failed = 'failed';
    case Expired = 'expired';

    // Silme.
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    /** Kullanıcının hâlâ bir şey bekleyebileceği hâller. */
    public function isOpen(): bool
    {
        return match ($this) {
            self::Queued, self::Running, self::Ready, self::Scheduled => true,
            self::Failed, self::Expired, self::Cancelled, self::Completed => false,
        };
    }
}
