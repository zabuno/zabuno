<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Billing\Exception\SubscriptionActionNotAllowedException;
use App\Application\Billing\Exception\WorkspaceNotFoundException;
use App\Application\Billing\UseCase\ManageSubscriptionLifecycle;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * İPTAL — müşteri kendi kendine çıkar (`docs/107` Faz 1.3, `docs/134` §1).
 *
 * Gövde YOKTUR: iptalin tek parametresi kimin ne zaman bastığıdır ve ikisi de
 * istekten okunur. Bir "sebep" alanı sorulmadı; uzaktan satışta çıkışın önüne
 * doldurulması gereken bir alan koymak, çıkışı zorlaştırmanın en kibar yoludur.
 */
final class StoreSubscriptionCancellationController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly ManageSubscriptionLifecycle $lifecycle,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::BillingManage, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        try {
            $summary = $this->lifecycle->cancel($workspace, $userId);
        } catch (WorkspaceNotFoundException) {
            return response()->json(['message' => 'Not Found.'], 404);
        } catch (SubscriptionActionNotAllowedException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'reason' => $exception->reason], 422);
        }

        return response()->json($summary->toArray());
    }
}
