<?php

declare(strict_types=1);

namespace App\Application\Support\UseCase;

use App\Application\Support\Dto\SupportRequestSummary;
use App\Application\Support\Port\SupportRequestRepositoryPort;
use App\Domain\Support\SupportRequestStatus;

/**
 * Süperadminin durum geçişi — FF-201 (`docs/125` §5).
 *
 * Geçiş kısıtı YOK (her durumdan her duruma): kapanmış bir talebin
 * yeniden açılması gerçek bir ihtiyaçtır ve "kapanan kapanır" kuralı bir
 * yanlış tıklamayı geri alınamaz yapardı. Tek değişmez, ilk yanıt
 * damgasının bir kez atılmasıdır — onu depo korur.
 */
final class ChangeSupportRequestStatus
{
    public function __construct(
        private readonly SupportRequestRepositoryPort $requests,
    ) {}

    public function handle(int $id, SupportRequestStatus $status): ?SupportRequestSummary
    {
        return $this->requests->changeStatus($id, $status);
    }
}
