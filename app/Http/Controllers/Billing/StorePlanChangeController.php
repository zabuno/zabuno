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
use Illuminate\Support\Facades\Validator;

/**
 * PLAN DÜŞÜRME — dönem SONUNDA, iadesiz (`docs/134` §2).
 *
 * YÜKSELTME BU UÇTAN GEÇMEZ ve 422 `not_a_downgrade` alır: yükseltmenin
 * karşılığı bir ödemedir ve yolu `POST /checkout`tur. Aynı uçtan geçseydi,
 * sahip bugün para ödemeden yarın daha fazlasını alacağını sanırdı.
 */
final class StorePlanChangeController extends Controller
{
    private const ALLOWED_FIELDS = ['plan_id'];

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

        $unexpected = array_diff(array_keys($request->all()), self::ALLOWED_FIELDS);

        if ($unexpected !== []) {
            return response()->json([
                'message' => 'Only plan_id is accepted.',
                'reason' => 'unexpected_fields',
            ], 422);
        }

        $validated = Validator::make($request->all(), [
            'plan_id' => ['required', 'integer', 'min:1'],
        ])->validate();

        try {
            $summary = $this->lifecycle->scheduleDowngrade($workspace, (int) $validated['plan_id'], $userId);
        } catch (WorkspaceNotFoundException) {
            return response()->json(['message' => 'Not Found.'], 404);
        } catch (SubscriptionActionNotAllowedException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'reason' => $exception->reason], 422);
        }

        return response()->json($summary->toArray());
    }
}
