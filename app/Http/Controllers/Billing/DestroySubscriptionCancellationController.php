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
 * İPTALDEN CAYMA (`docs/134` §1).
 *
 * Dönem bitmeden fikir değiştiren sahip YENİDEN ÖDEME YAPMAZ: silinen şey
 * iptal kaydıdır, abonelik hiç kesilmemiştir. Dönem bittikten sonra bu yol
 * kapanır ve 422 `period_over` ile söylenir — o noktada geri dönüş yolu
 * ödemedir, düğme değil.
 */
final class DestroySubscriptionCancellationController extends Controller
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
            $summary = $this->lifecycle->resume($workspace, $userId);
        } catch (WorkspaceNotFoundException) {
            return response()->json(['message' => 'Not Found.'], 404);
        } catch (SubscriptionActionNotAllowedException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'reason' => $exception->reason], 422);
        }

        return response()->json($summary->toArray());
    }
}
