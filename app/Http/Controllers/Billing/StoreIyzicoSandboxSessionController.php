<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Billing\Exception\IyzicoSandboxBadGatewayException;
use App\Application\Billing\Exception\IyzicoSandboxConflictException;
use App\Application\Billing\Exception\IyzicoSandboxUnavailableException;
use App\Application\Billing\UseCase\ManageIyzicoSandboxCheckout;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StoreIyzicoSandboxSessionController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly ManageIyzicoSandboxCheckout $checkout,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::BillingManage, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ($request->json()->all() !== []) {
            return response()->json(['message' => 'The request body must be an empty JSON object.'], 422);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        if (
            ! is_string($idempotencyKey)
            || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $idempotencyKey) !== 1
        ) {
            return response()->json(['message' => 'A valid UUID Idempotency-Key header is required.'], 422);
        }

        try {
            $session = $this->checkout->checkout($workspace, $userId, $idempotencyKey);
        } catch (IyzicoSandboxConflictException) {
            return response()->json(['message' => 'Conflict.'], 409);
        } catch (IyzicoSandboxUnavailableException) {
            return response()->json(['message' => 'Service Unavailable.'], 503);
        } catch (IyzicoSandboxBadGatewayException) {
            return response()->json(['message' => 'Bad Gateway.'], 502);
        }

        return response()->json($session->toArray(), 202);
    }
}
