<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Billing\Exception\BillingProfileMissingException;
use App\Application\Billing\Exception\CheckoutConflictException;
use App\Application\Billing\Exception\PaymentGatewayBadGatewayException;
use App\Application\Billing\Exception\PaymentGatewayUnavailableException;
use App\Application\Billing\Exception\PlanNotPurchasableException;
use App\Application\Billing\Exception\SubscriptionActionNotAllowedException;
use App\Application\Billing\UseCase\ManageCheckout;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Ödeme başlatma. Gövde YALNIZ plan_id + idempotency_key taşır: tutar,
 * para birimi ya da kart alanı taşıyan bir istek 422 ile geri döner —
 * istemciden gelen tutara güvenilmez (docs/09 §5).
 */
final class StoreCheckoutController extends Controller
{
    private const ALLOWED_FIELDS = ['plan_id', 'idempotency_key'];

    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly ManageCheckout $checkout,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::BillingManage, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $unexpected = array_diff(array_keys($request->all()), self::ALLOWED_FIELDS);

        if ($unexpected !== []) {
            return response()->json([
                'message' => 'Only plan_id and idempotency_key are accepted; the amount is read from the plan on the server.',
                'reason' => 'unexpected_fields',
            ], 422);
        }

        $validated = Validator::make($request->all(), [
            'plan_id' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
        ])->validate();

        try {
            $transaction = $this->checkout->checkout($workspace, $userId, (int) $validated['plan_id'], (string) $validated['idempotency_key']);
        } catch (PlanNotPurchasableException) {
            return response()->json(['message' => 'This plan cannot be purchased.', 'reason' => 'plan_not_purchasable'], 422);
        } catch (SubscriptionActionNotAllowedException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'reason' => $exception->reason], 422);
        } catch (BillingProfileMissingException) {
            return response()->json(['message' => 'Billing details are missing.', 'reason' => 'billing_profile_missing'], 422);
        } catch (CheckoutConflictException) {
            return response()->json(['message' => 'Conflict.'], 409);
        } catch (PaymentGatewayUnavailableException) {
            return response()->json(['message' => 'Service Unavailable.'], 503);
        } catch (PaymentGatewayBadGatewayException) {
            return response()->json(['message' => 'Bad Gateway.'], 502);
        }

        return response()->json($transaction->toArray(), 202);
    }
}
