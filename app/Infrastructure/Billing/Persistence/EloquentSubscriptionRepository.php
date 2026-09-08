<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Persistence;

use App\Application\Billing\Dto\ManualPaymentCommand;
use App\Application\Billing\Dto\ManualPaymentOutcome;
use App\Application\Billing\Dto\SubscriptionSummary;
use App\Application\Billing\Exception\ManualPaymentConflictException;
use App\Application\Billing\Exception\ManualPaymentUnavailableException;
use App\Application\Billing\Exception\SubscriptionActionNotAllowedException;
use App\Application\Billing\Exception\WorkspaceNotFoundException;
use App\Application\Billing\Port\SubscriptionRepositoryPort;
use App\Domain\Billing\SubscriptionLifecycle;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EloquentSubscriptionRepository implements SubscriptionRepositoryPort
{
    public function __construct(private readonly ConfigRepository $config) {}

    public function currentSubscription(int $workspaceId): SubscriptionSummary
    {
        if (! DB::table('workspaces')->where('id', $workspaceId)->exists()) {
            throw new WorkspaceNotFoundException;
        }

        return $this->loadCurrentSubscription($workspaceId);
    }

    public function recordManualPayment(ManualPaymentCommand $command): ManualPaymentOutcome
    {
        return DB::transaction(function () use ($command): ManualPaymentOutcome {
            $this->lockForWorkspace($command->workspaceId);

            if (! DB::table('workspaces')->where('id', $command->workspaceId)->exists()) {
                throw new WorkspaceNotFoundException;
            }

            $existing = DB::table('manual_payments')
                ->where('workspace_id', $command->workspaceId)
                ->where('idempotency_key', $command->idempotencyKey)
                ->first();

            if ($existing !== null) {
                return $this->replayOrConflict($existing, $command);
            }

            $plan = DB::table('plans')->where('id', $command->planId)->first();

            if ($plan === null || ! (bool) $plan->is_active) {
                throw new ManualPaymentUnavailableException('Plan is not available.');
            }

            $endsAt = Carbon::parse($command->endsAt);

            DB::table('manual_payments')->insert([
                'workspace_id' => $command->workspaceId,
                'actor_user_id' => $command->actorUserId,
                'plan_id' => $command->planId,
                'ends_at' => $endsAt,
                'payment_note' => $command->paymentNote,
                'document_reference' => $command->documentReference,
                'idempotency_key' => $command->idempotencyKey,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $subscriptionExists = DB::table('subscriptions')
                ->where('workspace_id', $command->workspaceId)
                ->exists();

            if ($subscriptionExists) {
                DB::table('subscriptions')->where('workspace_id', $command->workspaceId)->update([
                    'plan_id' => $command->planId,
                    'state' => 'active',
                    'ends_at' => $endsAt,
                    // Elle kaydedilen bir tahsilat da bir ödemedir: iptal ve
                    // zamanlanmış düşürme aynı sebeple silinir (bkz. port).
                    ...self::PAYMENT_CLEARS,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('subscriptions')->insert([
                    'workspace_id' => $command->workspaceId,
                    'plan_id' => $command->planId,
                    'state' => 'active',
                    'ends_at' => $endsAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return new ManualPaymentOutcome($this->loadCurrentSubscription($command->workspaceId), false);
        });
    }

    public function extendFromPayment(int $workspaceId, int $planId, int $periodDays): array
    {
        return DB::transaction(function () use ($workspaceId, $planId, $periodDays): array {
            $this->lockForWorkspace($workspaceId);

            $existing = DB::table('subscriptions')->where('workspace_id', $workspaceId)->first();
            $before = $existing === null ? null : Carbon::parse($existing->ends_at);

            /*
                Kalan gün KAYBOLMAZ: bitiş ileride ise onun üstüne, geçmişte
                kaldıysa bugünden. Erken ödeyen bir sahip, erken ödediği için
                gün kaybetmemeli.
            */
            $base = $before !== null && $before->greaterThan(now()) ? $before->copy() : now();
            $after = $base->addDays($periodDays);

            if ($existing === null) {
                DB::table('subscriptions')->insert([
                    'workspace_id' => $workspaceId,
                    'plan_id' => $planId,
                    'state' => 'active',
                    'ends_at' => $after,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('subscriptions')->where('workspace_id', $workspaceId)->update([
                    'plan_id' => $planId,
                    'state' => 'active',
                    'ends_at' => $after,
                    /*
                        ASKIDAN DÖNÜŞ ELLE MÜDAHALE İSTEMEZ.

                        İptal ve zamanlanmış düşürme burada silinir: sahip
                        kartını çıkarıp yeni bir dönem satın aldıysa, çıkma
                        niyeti de bir sonraki döneme dair plan kararı da
                        geçmişte kalmıştır. Ödenen plan, geçerli plandır.
                    */
                    ...self::PAYMENT_CLEARS,
                    'updated_at' => now(),
                ]);
            }

            return [
                'before' => $before?->toIso8601String(),
                'after' => $after->toIso8601String(),
            ];
        });
    }

    public function shortenAfterRefund(int $workspaceId, int $periodDays): array
    {
        return DB::transaction(function () use ($workspaceId, $periodDays): array {
            $this->lockForWorkspace($workspaceId);

            $existing = DB::table('subscriptions')->where('workspace_id', $workspaceId)->first();

            if ($existing === null) {
                return ['before' => null, 'after' => null];
            }

            $before = Carbon::parse($existing->ends_at);
            $after = $before->copy()->subDays($periodDays);

            DB::table('subscriptions')->where('workspace_id', $workspaceId)->update([
                'ends_at' => $after,
                'updated_at' => now(),
            ]);

            return ['before' => $before->toIso8601String(), 'after' => $after->toIso8601String()];
        });
    }

    public function cancel(int $workspaceId, int $actorUserId): SubscriptionSummary
    {
        return DB::transaction(function () use ($workspaceId, $actorUserId): SubscriptionSummary {
            $this->lockForWorkspace($workspaceId);
            $row = $this->requireSubscriptionRow($workspaceId);

            if ($row->cancelled_at !== null) {
                throw SubscriptionActionNotAllowedException::alreadyCancelled();
            }

            if (! $this->lifecycleFor($row)->phase->canCancel()) {
                throw SubscriptionActionNotAllowedException::periodOver();
            }

            DB::table('subscriptions')->where('workspace_id', $workspaceId)->update([
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $actorUserId,
                // Çıkan sahip bir sonraki dönem için plan seçmiyor.
                'scheduled_plan_id' => null,
                'scheduled_by_user_id' => null,
                'scheduled_at' => null,
                'updated_at' => now(),
            ]);

            return $this->loadCurrentSubscription($workspaceId);
        });
    }

    public function resumeCancelled(int $workspaceId): SubscriptionSummary
    {
        return DB::transaction(function () use ($workspaceId): SubscriptionSummary {
            $this->lockForWorkspace($workspaceId);
            $row = $this->requireSubscriptionRow($workspaceId);

            if ($row->cancelled_at === null) {
                throw SubscriptionActionNotAllowedException::notCancelled();
            }

            /*
                CAYMA YALNIZ DÖNEM BİTMEDEN. Bitmiş bir dönemin iptalinden
                caymak, ödenmemiş bir dönemi ücretsiz açmak olurdu; o yol
                ödemedir, düğme değil.
            */
            if (! $this->lifecycleFor($row)->phase->canResume()) {
                throw SubscriptionActionNotAllowedException::periodOver();
            }

            DB::table('subscriptions')->where('workspace_id', $workspaceId)->update([
                'cancelled_at' => null,
                'cancelled_by_user_id' => null,
                'updated_at' => now(),
            ]);

            return $this->loadCurrentSubscription($workspaceId);
        });
    }

    public function schedulePlanChange(int $workspaceId, int $targetPlanId, int $actorUserId): SubscriptionSummary
    {
        return DB::transaction(function () use ($workspaceId, $targetPlanId, $actorUserId): SubscriptionSummary {
            $this->lockForWorkspace($workspaceId);
            $row = $this->requireSubscriptionRow($workspaceId);

            if ($row->cancelled_at !== null) {
                throw SubscriptionActionNotAllowedException::alreadyCancelled();
            }

            $lifecycle = $this->lifecycleFor($row);

            if (! $lifecycle->phase->canCancel()) {
                throw SubscriptionActionNotAllowedException::periodOver();
            }

            $target = DB::table('plans')->where('id', $targetPlanId)->where('is_active', true)->first();
            $current = DB::table('plans')->where('id', (int) $row->plan_id)->first();

            if ($target === null || $current === null) {
                throw SubscriptionActionNotAllowedException::planUnavailable();
            }

            if ((int) $target->id === (int) $current->id || ! $this->isDowngrade($current, $target)) {
                /*
                    YÜKSELTME BURADAN GEÇMEZ ve bu bir kısıtlama değil, bir
                    dürüstlük: yükseltme ANINDA başlar ve karşılığı bir
                    ödemedir. Zamanlanmış "yükseltme", sahibin bugün para
                    ödemeden yarın daha fazlasını alacağını sanmasıdır.
                */
                throw SubscriptionActionNotAllowedException::notADowngrade();
            }

            DB::table('subscriptions')->where('workspace_id', $workspaceId)->update([
                'scheduled_plan_id' => $targetPlanId,
                'scheduled_by_user_id' => $actorUserId,
                'scheduled_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->loadCurrentSubscription($workspaceId);
        });
    }

    public function cancelScheduledPlanChange(int $workspaceId): SubscriptionSummary
    {
        return DB::transaction(function () use ($workspaceId): SubscriptionSummary {
            $this->lockForWorkspace($workspaceId);
            $row = $this->requireSubscriptionRow($workspaceId);

            if ($row->scheduled_plan_id === null) {
                throw SubscriptionActionNotAllowedException::nothingScheduled();
            }

            DB::table('subscriptions')->where('workspace_id', $workspaceId)->update([
                'scheduled_plan_id' => null,
                'scheduled_by_user_id' => null,
                'scheduled_at' => null,
                'updated_at' => now(),
            ]);

            return $this->loadCurrentSubscription($workspaceId);
        });
    }

    // --- iç yardımcılar ---------------------------------------------------

    /**
     * Bir ödemenin sildiği niyetler — tek yerde, çünkü iki yazma yolu var
     * (kendi kendine ödeme ve elle kaydedilen tahsilat).
     */
    private const PAYMENT_CLEARS = [
        'cancelled_at' => null,
        'cancelled_by_user_id' => null,
        'scheduled_plan_id' => null,
        'scheduled_by_user_id' => null,
        'scheduled_at' => null,
    ];

    /**
     * Fiyatsız plan bir DÜŞÜRME HEDEFİ değildir ve karşılaştırmaya girmez:
     * fiyatı olmayan plan zaten satın alınamaz (`purchasablePlan`), yani
     * ona "inmek" bedava bir plana geçmek olurdu ve o karar bu ekranın
     * değil, aboneliği bitirmenin (iptal) kararıdır.
     */
    private function isDowngrade(object $current, object $target): bool
    {
        if ($current->amount_minor === null || $target->amount_minor === null) {
            return false;
        }

        return (int) $target->amount_minor < (int) $current->amount_minor;
    }

    private function lockForWorkspace(int $workspaceId): void
    {
        DB::table('workspaces')->where('id', $workspaceId)->lockForUpdate()->exists();
    }

    private function requireSubscriptionRow(int $workspaceId): object
    {
        if (! DB::table('workspaces')->where('id', $workspaceId)->exists()) {
            throw new WorkspaceNotFoundException;
        }

        $row = DB::table('subscriptions')->where('workspace_id', $workspaceId)->first();

        if ($row === null) {
            throw SubscriptionActionNotAllowedException::noSubscription();
        }

        return $row;
    }

    private function lifecycleFor(object $row): SubscriptionLifecycle
    {
        return SubscriptionLifecycle::of(
            Carbon::parse($row->ends_at),
            $row->cancelled_at === null ? null : Carbon::parse($row->cancelled_at),
            $this->graceDays(),
            $this->periodDays(),
            Carbon::now(),
        );
    }

    private function graceDays(): int
    {
        $days = (int) $this->config->get('billing.subscription.grace_days', 0);

        return max($days, 0);
    }

    private function periodDays(): int
    {
        $days = (int) $this->config->get('billing.subscription.period_days', 30);

        return $days > 0 ? $days : 30;
    }

    private function replayOrConflict(object $existing, ManualPaymentCommand $command): ManualPaymentOutcome
    {
        $matches = (int) $existing->plan_id === $command->planId
            && Carbon::parse($existing->ends_at)->equalTo(Carbon::parse($command->endsAt))
            && (string) $existing->payment_note === $command->paymentNote
            && (string) $existing->document_reference === $command->documentReference;

        if (! $matches) {
            throw new ManualPaymentConflictException('idempotency_key already used with a different payload.');
        }

        return new ManualPaymentOutcome($this->loadCurrentSubscription($command->workspaceId), true);
    }

    private function loadCurrentSubscription(int $workspaceId): SubscriptionSummary
    {
        $row = DB::table('subscriptions')->where('workspace_id', $workspaceId)->first();

        if ($row === null) {
            return SubscriptionSummary::none();
        }

        $lifecycle = $this->lifecycleFor($row);
        $scheduledPlanId = $row->scheduled_plan_id === null ? null : (int) $row->scheduled_plan_id;
        $effectivePlanId = $lifecycle->effectivePlanId((int) $row->plan_id, $scheduledPlanId);

        $plan = DB::table('plans')->where('id', $effectivePlanId)->first();

        if ($plan === null) {
            throw new RuntimeException("Workspace [{$workspaceId}] has a subscription referencing a missing plan.");
        }

        $scheduled = $scheduledPlanId === null || $scheduledPlanId === $effectivePlanId
            ? null
            : DB::table('plans')->where('id', $scheduledPlanId)->first();

        return SubscriptionSummary::of(
            $lifecycle->phase,
            (int) $plan->id,
            (string) $plan->code,
            (string) $plan->name,
            (int) $plan->version,
            Carbon::parse($row->ends_at)->toIso8601String(),
            $row->cancelled_at === null ? null : Carbon::parse($row->cancelled_at)->toIso8601String(),
            $lifecycle->graceEndsAt?->format(DATE_ATOM),
            $scheduled === null ? null : (int) $scheduled->id,
            $scheduled === null ? null : (string) $scheduled->code,
            $scheduled === null ? null : (string) $scheduled->name,
        );
    }
}
