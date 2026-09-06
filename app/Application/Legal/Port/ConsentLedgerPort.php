<?php

declare(strict_types=1);

namespace App\Application\Legal\Port;

use App\Domain\Legal\ConsentRecord;

/**
 * Onay defteri — YALNIZ EKLENİR (FF-198).
 *
 * Güncelleme ya da silme yok: düzeltilebilen bir onay kaydı, kanıt değildir.
 * Geri alma yeni bir satırdır (`granted = false`).
 */
interface ConsentLedgerPort
{
    public function append(ConsentRecord $record): void;
}
