<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Billing\Exception\PaymentGatewayBadGatewayException;
use App\Application\Billing\Exception\PaymentGatewayUnavailableException;
use App\Application\Billing\Exception\PaymentTransactionNotFoundException;
use App\Application\Billing\Exception\RefundNotAllowedException;
use App\Application\Billing\Exception\RefundRejectedException;
use App\Application\Billing\UseCase\ManageCheckout;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** İade — süperadmin, sebep zorunlu (docs/107 Faz 1.1). */
final class RefundPaymentTransactionController extends Controller
{
    public function __construct(private readonly ManageCheckout $checkout) {}

    public function __invoke(Request $request, int $workspace, int $transaction): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ])->validate();

        try {
            $refunded = $this->checkout->refund($workspace, $transaction, (int) $request->user()->getKey(), trim((string) $validated['reason']));
        } catch (PaymentTransactionNotFoundException) {
            return response()->json(['message' => 'Not Found.'], 404);
        } catch (RefundNotAllowedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (RefundRejectedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        } catch (PaymentGatewayBadGatewayException) {
            return response()->json(['message' => 'Bad Gateway.'], 502);
        } catch (PaymentGatewayUnavailableException) {
            return response()->json(['message' => 'Service Unavailable.'], 503);
        }

        return response()->json($refunded->toArray());
    }
}
