<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Persistence;

use App\Application\Billing\Dto\ManualPaymentCommand;
use App\Application\Billing\Dto\ManualPaymentOutcome;
use App\Application\Billing\Dto\SubscriptionSummary;
use App\Application\Billing\Exception\ManualPaymentConflictException;
use App\Application\Billing\Exception\ManualPaymentUnavailableException;
use App\Application\Billing\Exception\WorkspaceNotFoundException;
use App\Application\Billing\Port\SubscriptionRepositoryPort;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EloquentSubscriptionRepository implements SubscriptionRepositoryPort
{
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

    private function lockForWorkspace(int $workspaceId): void
    {
        DB::table('workspaces')->where('id', $workspaceId)->lockForUpdate()->exists();
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
        $row = DB::table('subscriptions')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('subscriptions.workspace_id', $workspaceId)
            ->select([
                'subscriptions.state as state',
                'subscriptions.ends_at as ends_at',
                'plans.id as plan_id',
                'plans.code as plan_code',
                'plans.name as plan_name',
                'plans.version as plan_version',
            ])
            ->first();

        if ($row === null) {
            $orphaned = DB::table('subscriptions')->where('workspace_id', $workspaceId)->exists();

            if ($orphaned) {
                throw new RuntimeException("Workspace [{$workspaceId}] has a subscription referencing a missing plan.");
            }

            return SubscriptionSummary::none();
        }

        return SubscriptionSummary::active(
            (int) $row->plan_id,
            (string) $row->plan_code,
            (string) $row->plan_name,
            (int) $row->plan_version,
            Carbon::parse($row->ends_at)->toIso8601String(),
        );
    }
}
