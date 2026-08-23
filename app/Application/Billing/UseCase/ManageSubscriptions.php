<?php

declare(strict_types=1);

namespace App\Application\Billing\UseCase;

use App\Application\Billing\Dto\ManualPaymentCommand;
use App\Application\Billing\Dto\ManualPaymentOutcome;
use App\Application\Billing\Dto\SubscriptionSummary;
use App\Application\Billing\Exception\ManualPaymentConflictException;
use App\Application\Billing\Exception\ManualPaymentUnavailableException;
use App\Application\Billing\Exception\WorkspaceNotFoundException;
use App\Application\Billing\Port\SubscriptionRepositoryPort;

final class ManageSubscriptions
{
    public function __construct(
        private readonly SubscriptionRepositoryPort $subscriptions,
    ) {}

    /**
     * @throws WorkspaceNotFoundException
     */
    public function current(int $workspaceId): SubscriptionSummary
    {
        return $this->subscriptions->currentSubscription($workspaceId);
    }

    /**
     * @throws WorkspaceNotFoundException
     * @throws ManualPaymentUnavailableException
     * @throws ManualPaymentConflictException
     */
    public function recordManualPayment(ManualPaymentCommand $command): ManualPaymentOutcome
    {
        return $this->subscriptions->recordManualPayment($command);
    }
}
