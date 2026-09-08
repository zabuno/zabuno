<?php

declare(strict_types=1);

namespace App\Application\Billing\Exception;

use RuntimeException;

/**
 * İptal, iptalden cayma ya da plan düşürme, aboneliğin BUGÜNKÜ evresinde
 * yapılamaz (`SubscriptionPhase`).
 *
 * Sebep bir dizedir ve DIŞARI ADIYLA çıkar (`reason`): "olmadı" demek,
 * sahibin ne yapması gerektiğini söylemez.
 */
final class SubscriptionActionNotAllowedException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function noSubscription(): self
    {
        return new self('no_subscription', 'There is no subscription to change.');
    }

    public static function alreadyCancelled(): self
    {
        return new self('already_cancelled', 'The subscription is already cancelled.');
    }

    public static function notCancelled(): self
    {
        return new self('not_cancelled', 'The subscription is not cancelled, so there is nothing to resume.');
    }

    public static function periodOver(): self
    {
        return new self('period_over', 'The paid period is over; pay for a plan instead of scheduling a change.');
    }

    public static function planUnavailable(): self
    {
        return new self('plan_unavailable', 'The chosen plan is not available.');
    }

    public static function notADowngrade(): self
    {
        return new self('not_a_downgrade', 'Moving to this plan is not a downgrade; pay for it to start it now.');
    }

    public static function downgradeRequiresSchedule(): self
    {
        return new self(
            'downgrade_requires_schedule',
            'A cheaper plan cannot be paid for while a paid period is running; schedule the downgrade instead.',
        );
    }

    public static function nothingScheduled(): self
    {
        return new self('nothing_scheduled', 'No plan change is scheduled.');
    }
}
