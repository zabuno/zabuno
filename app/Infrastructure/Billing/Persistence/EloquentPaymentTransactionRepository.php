<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Persistence;

use App\Application\Billing\Dto\PaymentTransaction;
use App\Application\Billing\Exception\CheckoutConflictException;
use App\Application\Billing\Port\PaymentTransactionRepositoryPort;
use App\Domain\Billing\BillingMode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class EloquentPaymentTransactionRepository implements PaymentTransactionRepositoryPort
{
    private const TABLE = 'payment_transactions';

    public function purchasablePlan(int $planId): ?array
    {
        $row = DB::table('plans')
            ->where('id', $planId)
            ->where('is_active', true)
            ->whereNotNull('amount_minor')
            ->whereNotNull('currency')
            ->first(['id', 'amount_minor', 'currency']);

        if ($row === null) {
            return null;
        }

        return [
            'plan_id' => (int) $row->id,
            'amount_minor' => (int) $row->amount_minor,
            'currency' => (string) $row->currency,
        ];
    }

    public function latestFor(int $workspaceId): ?PaymentTransaction
    {
        $row = DB::table(self::TABLE)
            ->where('workspace_id', $workspaceId)
            ->where('state', '!=', 'reserved')
            ->orderByDesc('id')
            ->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?PaymentTransaction
    {
        $row = DB::table(self::TABLE)->where('idempotency_key', $idempotencyKey)->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function findByConversationId(string $conversationId): ?PaymentTransaction
    {
        $row = DB::table(self::TABLE)->where('conversation_id', $conversationId)->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function findByToken(string $token): ?PaymentTransaction
    {
        $row = DB::table(self::TABLE)->where('token', $token)->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function findForWorkspace(int $workspaceId, int $id): ?PaymentTransaction
    {
        $row = DB::table(self::TABLE)->where('workspace_id', $workspaceId)->where('id', $id)->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function claim(
        int $workspaceId,
        int $actorUserId,
        int $planId,
        BillingMode $mode,
        string $idempotencyKey,
        string $conversationId,
        int $amountMinor,
        string $currency,
        int $periodDays,
    ): bool {
        $row = [
            'workspace_id' => $workspaceId,
            'actor_user_id' => $actorUserId,
            'plan_id' => $planId,
            'mode' => $mode->value,
            'idempotency_key' => $idempotencyKey,
            'conversation_id' => $conversationId,
            'token' => null,
            'redirect_url' => null,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'period_days' => $periodDays,
            'state' => 'reserved',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        try {
            // SAVEPOINT: PostgreSQL'de başarısız INSERT dış işlemi zehirler
            // (25P02); savepoint yalnız kendi kapsamını geri sarar.
            DB::transaction(static fn () => DB::table(self::TABLE)->insert($row));
        } catch (QueryException) {
            return false;
        }

        return true;
    }

    public function complete(string $idempotencyKey, string $token, string $redirectUrl): PaymentTransaction
    {
        $row = DB::table(self::TABLE)->where('idempotency_key', $idempotencyKey)->first();

        if ($row === null) {
            throw new CheckoutConflictException('idempotency_key reservation not found.');
        }

        DB::table(self::TABLE)->where('id', $row->id)->update([
            'token' => $token,
            'redirect_url' => $redirectUrl,
            'state' => 'initiated',
            'updated_at' => now(),
        ]);

        return $this->hydrate(DB::table(self::TABLE)->where('id', $row->id)->first());
    }

    public function markSucceeded(int $id, ?string $referenceCode, string $paymentId, ?string $paymentTransactionId, string $token): bool
    {
        return DB::table(self::TABLE)
            ->where('id', $id)
            ->whereNotIn('state', PaymentTransaction::TERMINAL_STATES)
            ->update([
                'state' => 'succeeded',
                'reference_code' => $referenceCode,
                'payment_id' => $paymentId,
                'payment_transaction_id' => $paymentTransactionId,
                'token' => $token,
                'updated_at' => now(),
            ]) === 1;
    }

    public function markFailed(int $id, ?string $referenceCode, ?string $paymentId, ?string $token, ?string $failureReason): bool
    {
        $values = [
            'state' => 'failed',
            'failure_reason' => $failureReason === null ? null : mb_substr($failureReason, 0, 500),
            'updated_at' => now(),
        ];

        if ($referenceCode !== null) {
            $values['reference_code'] = $referenceCode;
        }
        if ($paymentId !== null && $paymentId !== '') {
            $values['payment_id'] = $paymentId;
        }
        if ($token !== null) {
            $values['token'] = $token;
        }

        return DB::table(self::TABLE)
            ->where('id', $id)
            ->whereNotIn('state', PaymentTransaction::TERMINAL_STATES)
            ->update($values) === 1;
    }

    public function recordSubscriptionWindow(int $id, ?string $before, ?string $after): void
    {
        DB::table(self::TABLE)->where('id', $id)->update([
            'subscription_ends_at_before' => $before === null ? null : Carbon::parse($before),
            'subscription_ends_at_after' => $after === null ? null : Carbon::parse($after),
            'updated_at' => now(),
        ]);
    }

    public function markRefunded(int $id, string $reason, int $byUserId): bool
    {
        return DB::table(self::TABLE)
            ->where('id', $id)
            ->where('state', 'succeeded')
            ->update([
                'state' => 'refunded',
                'refund_reason' => mb_substr($reason, 0, 500),
                'refunded_by_user_id' => $byUserId,
                'refunded_at' => now(),
                'updated_at' => now(),
            ]) === 1;
    }

    private function hydrate(object $row): PaymentTransaction
    {
        return new PaymentTransaction(
            id: (int) $row->id,
            workspaceId: (int) $row->workspace_id,
            actorUserId: (int) $row->actor_user_id,
            planId: (int) $row->plan_id,
            mode: (string) $row->mode,
            conversationId: (string) $row->conversation_id,
            token: $row->token === null ? null : (string) $row->token,
            redirectUrl: $row->redirect_url === null ? null : (string) $row->redirect_url,
            amountMinor: (int) $row->amount_minor,
            currency: (string) $row->currency,
            periodDays: (int) $row->period_days,
            state: (string) $row->state,
            referenceCode: $row->reference_code === null ? null : (string) $row->reference_code,
            paymentId: $row->payment_id === null ? null : (string) $row->payment_id,
            paymentTransactionId: $row->payment_transaction_id === null ? null : (string) $row->payment_transaction_id,
            failureReason: $row->failure_reason === null ? null : (string) $row->failure_reason,
            refundReason: $row->refund_reason === null ? null : (string) $row->refund_reason,
            refundedByUserId: $row->refunded_by_user_id === null ? null : (int) $row->refunded_by_user_id,
            refundedAt: $row->refunded_at === null ? null : Carbon::parse($row->refunded_at)->toIso8601String(),
            createdAt: $row->created_at === null ? null : Carbon::parse($row->created_at)->toIso8601String(),
        );
    }
}
