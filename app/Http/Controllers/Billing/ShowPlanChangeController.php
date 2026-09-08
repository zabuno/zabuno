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
 * "Bu plana geçersem NE KAYBEDERİM?" — karar vermeden ÖNCE (`docs/134` §2).
 *
 * Bir SORGUDUR, kapı değil: hiçbir şey değiştirmez. Cevabı sunucu üretir
 * çünkü yetenek etiketlerinin tek sahibi `Entitlement` enum'udur; arayüzde
 * ikinci bir etiket listesi tutmak, plan bir yetenek kazandığında sessizce
 * ayrışan bir metin üretirdi.
 */
final class ShowPlanChangeController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly ManageSubscriptionLifecycle $lifecycle,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::BillingView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $validated = Validator::make($request->query(), [
            'plan_id' => ['required', 'integer', 'min:1'],
        ])->validate();

        try {
            $preview = $this->lifecycle->previewPlanChange($workspace, (int) $validated['plan_id']);
        } catch (WorkspaceNotFoundException) {
            return response()->json(['message' => 'Not Found.'], 404);
        } catch (SubscriptionActionNotAllowedException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'reason' => $exception->reason], 422);
        }

        return response()->json($preview->toArray());
    }
}
