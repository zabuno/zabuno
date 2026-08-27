<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Persistence;

use App\Application\Billing\Dto\IyzicoSandboxSession;
use App\Application\Billing\Exception\IyzicoSandboxConflictException;
use App\Application\Billing\Port\IyzicoSandboxTransactionRepositoryPort;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class EloquentIyzicoSandboxTransactionRepository implements IyzicoSandboxTransactionRepositoryPort
{
    public function sessionState(int $workspaceId): IyzicoSandboxSession
    {
        $row = DB::table('iyzico_sandbox_transactions')
            ->where('workspace_id', $workspaceId)
            ->where('state', '!=', 'reserved')
            ->orderByDesc('id')
            ->first();

        if ($row === null) {
            return IyzicoSandboxSession::none();
        }

        return new IyzicoSandboxSession(
            (string) $row->state,
            (string) $row->conversation_id,
            (int) $row->amount_minor,
            (string) $row->currency,
            $row->redirect_url !== null ? (string) $row->redirect_url : null,
        );
    }

    public function activeSubscriptionPlan(int $workspaceId): ?array
    {
        $row = DB::table('subscriptions')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('subscriptions.workspace_id', $workspaceId)
            ->where('subscriptions.state', 'active')
            ->whereDate('subscriptions.ends_at', '>=', now()->toDateString())
            ->where('plans.is_active', true)
            ->select(['plans.id as plan_id', 'plans.amount_minor as amount_minor', 'plans.currency as currency'])
            ->first();

        if ($row === null || $row->amount_minor === null || $row->currency === null) {
            return null;
        }

        return [
            'plan_id' => (int) $row->plan_id,
            'amount_minor' => (int) $row->amount_minor,
            'currency' => (string) $row->currency,
        ];
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?array
    {
        $row = DB::table('iyzico_sandbox_transactions')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'workspace_id' => (int) $row->workspace_id,
            'conversation_id' => (string) $row->conversation_id,
            'amount_minor' => (int) $row->amount_minor,
            'currency' => (string) $row->currency,
            'redirect_url' => $row->redirect_url !== null ? (string) $row->redirect_url : null,
            'state' => (string) $row->state,
        ];
    }

    public function claimInitialization(
        int $workspaceId,
        int $actorUserId,
        int $planId,
        string $idempotencyKey,
        string $conversationId,
        int $amountMinor,
        string $currency,
    ): bool {
        $row = [
            'workspace_id' => $workspaceId,
            'actor_user_id' => $actorUserId,
            'plan_id' => $planId,
            'idempotency_key' => $idempotencyKey,
            'conversation_id' => $conversationId,
            'token' => null,
            'redirect_url' => null,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'state' => 'reserved',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        try {
            // İç içe işlem = SAVEPOINT. PostgreSQL'de başarısız bir INSERT
            // içinde bulunduğu işlemin TAMAMINI zehirler (SQLSTATE 25P02):
            // sonraki her sorgu, işlem kapanana kadar reddedilir. SQLite
            // böyle davranmadığı için bu desen yıllarca çalışıyor göründü.
            // Savepoint, başarısızlığı yalnız kendi kapsamına geri sarar.
            DB::transaction(
                static fn () => DB::table('iyzico_sandbox_transactions')->insert($row)
            );
        } catch (QueryException) {
            return false;
        }

        return true;
    }

    public function completeInitialization(
        string $idempotencyKey,
        string $token,
        string $redirectUrl,
    ): IyzicoSandboxSession {
        $row = DB::table('iyzico_sandbox_transactions')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($row === null) {
            throw new IyzicoSandboxConflictException('idempotency_key reservation not found.');
        }

        DB::table('iyzico_sandbox_transactions')->where('id', $row->id)->update([
            'token' => $token,
            'redirect_url' => $redirectUrl,
            'state' => 'initiated',
            'updated_at' => now(),
        ]);

        return new IyzicoSandboxSession('initiated', (string) $row->conversation_id, (int) $row->amount_minor, (string) $row->currency, $redirectUrl);
    }

    public function findByConversationId(string $conversationId): ?array
    {
        $row = DB::table('iyzico_sandbox_transactions')
            ->where('conversation_id', $conversationId)
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'workspace_id' => (int) $row->workspace_id,
            'conversation_id' => (string) $row->conversation_id,
            'token' => $row->token !== null ? (string) $row->token : null,
            'amount_minor' => (int) $row->amount_minor,
            'currency' => (string) $row->currency,
            'state' => (string) $row->state,
            'reference_code' => $row->reference_code !== null ? (string) $row->reference_code : null,
        ];
    }

    public function findByToken(string $token): ?array
    {
        $row = DB::table('iyzico_sandbox_transactions')
            ->where('token', $token)
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'workspace_id' => (int) $row->workspace_id,
            'conversation_id' => (string) $row->conversation_id,
            'token' => (string) $row->token,
            'amount_minor' => (int) $row->amount_minor,
            'currency' => (string) $row->currency,
            'state' => (string) $row->state,
        ];
    }

    public function transitionToTerminal(
        int $id,
        string $state,
        ?string $referenceCode,
        string $paymentId,
        string $token,
    ): void {
        DB::table('iyzico_sandbox_transactions')
            ->where('id', $id)
            ->whereNotIn('state', ['succeeded', 'failed'])
            ->update([
                'state' => $state,
                'reference_code' => $referenceCode,
                'payment_id' => $paymentId,
                'token' => $token,
                'updated_at' => now(),
            ]);
    }
}
