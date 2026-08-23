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
}
