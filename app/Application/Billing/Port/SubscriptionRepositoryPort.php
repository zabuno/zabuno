<?php

declare(strict_types=1);

namespace App\Application\Billing\Port;

use App\Application\Billing\Dto\ManualPaymentCommand;
use App\Application\Billing\Dto\ManualPaymentOutcome;
use App\Application\Billing\Dto\SubscriptionSummary;
use App\Application\Billing\Exception\ManualPaymentConflictException;
use App\Application\Billing\Exception\ManualPaymentUnavailableException;
use App\Application\Billing\Exception\WorkspaceNotFoundException;

interface SubscriptionRepositoryPort
{
    /**
     * @throws WorkspaceNotFoundException
     */
    public function currentSubscription(int $workspaceId): SubscriptionSummary;

    /**
     * @throws WorkspaceNotFoundException
     * @throws ManualPaymentUnavailableException
     * @throws ManualPaymentConflictException
     */
    public function recordManualPayment(ManualPaymentCommand $command): ManualPaymentOutcome;

    /**
     * Başarılı bir ödeme aboneliği bir dönem ileri taşır: varsa bitişin
     * ÜSTÜNE (kalan gün kaybolmaz), yoksa bugünden. Ödenen plan geçerli
     * plan olur. Dönüş: önceki ve yeni bitiş (ISO-8601), önceki yoksa null.
     *
     * @return array{before: ?string, after: string}
     */
    public function extendFromPayment(int $workspaceId, int $planId, int $periodDays): array;

    /**
     * İade edilen dönem bitişten düşülür. Abonelik yoksa ikisi de null.
     *
     * @return array{before: ?string, after: ?string}
     */
    public function shortenAfterRefund(int $workspaceId, int $periodDays): array;
}
