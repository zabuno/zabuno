<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Billing\Dto\ManualPaymentCommand;
use App\Application\Billing\Exception\ManualPaymentConflictException;
use App\Application\Billing\Exception\ManualPaymentUnavailableException;
use App\Application\Billing\Exception\WorkspaceNotFoundException;
use App\Application\Billing\UseCase\ManageSubscriptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreManualPaymentRequest;
use Illuminate\Http\JsonResponse;

final class StoreManualPaymentController extends Controller
{
    public function __construct(
        private readonly ManageSubscriptions $subscriptions,
    ) {}

    public function __invoke(StoreManualPaymentRequest $request, int $workspace): JsonResponse
    {
        $command = new ManualPaymentCommand(
            $workspace,
            (int) $request->user()->getKey(),
            (int) $request->validated('plan_id'),
            (string) $request->validated('ends_at'),
            (string) $request->validated('payment_note'),
            (string) $request->validated('document_reference'),
            (string) $request->validated('idempotency_key'),
        );

        try {
            $outcome = $this->subscriptions->recordManualPayment($command);
        } catch (WorkspaceNotFoundException) {
            return response()->json(['message' => 'Not Found.'], 404);
        } catch (ManualPaymentUnavailableException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (ManualPaymentConflictException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json($outcome->toArray(), $outcome->wasReplay ? 200 : 201);
    }
}
