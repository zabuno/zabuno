<?php

declare(strict_types=1);

namespace App\Application\Billing\Port;

use App\Application\Billing\Dto\ManualPaymentCommand;
use App\Application\Billing\Dto\ManualPaymentOutcome;
use App\Application\Billing\Dto\SubscriptionSummary;
use App\Application\Billing\Exception\ManualPaymentConflictException;
use App\Application\Billing\Exception\ManualPaymentUnavailableException;
use App\Application\Billing\Exception\SubscriptionActionNotAllowedException;
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
     * ÖDEME, İPTALİ VE ZAMANLANMIŞ DÜŞÜRMEYİ SİLER. Sahip kartını çıkarıp
     * yeni bir dönem satın aldıysa çıkma niyeti geçmişte kalmıştır; askıdaki
     * bir hesap da aynı yoldan, elle müdahale olmadan geri döner.
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

    /**
     * İPTAL — yenileme durur, ödenmiş dönem SÜRER.
     *
     * `state` sütununa dokunulmaz ve bu iptalin bütün sözüdür: hizmet
     * `ends_at`'e kadar aynen devam eder. Zamanlanmış bir düşürme varsa
     * silinir — çıkan sahip bir sonraki dönem için plan seçmiyor.
     *
     * @throws SubscriptionActionNotAllowedException
     */
    public function cancel(int $workspaceId, int $actorUserId): SubscriptionSummary;

    /**
     * İPTALDEN CAYMA — dönem bitmeden fikir değiştiren sahip yeniden ödeme
     * yapmak zorunda kalmaz.
     *
     * @throws SubscriptionActionNotAllowedException
     */
    public function resumeCancelled(int $workspaceId): SubscriptionSummary;

    /**
     * PLAN DÜŞÜRME — dönem SONUNDA yürürlüğe girer, fark iade edilmez.
     *
     * @throws SubscriptionActionNotAllowedException
     */
    public function schedulePlanChange(int $workspaceId, int $targetPlanId, int $actorUserId): SubscriptionSummary;

    /**
     * Zamanlanmış düşürmeden vazgeçme.
     *
     * @throws SubscriptionActionNotAllowedException
     */
    public function cancelScheduledPlanChange(int $workspaceId): SubscriptionSummary;
}
