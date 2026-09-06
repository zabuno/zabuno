<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Billing\Exception\BillingModeRejectedException;
use App\Application\Billing\UseCase\ManageBillingMode;
use App\Domain\Billing\BillingMode;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Kip anahtarı (docs/123). `live` kasa boşken ya da dağıtım izin vermezken
 * 422 ile ve KAPININ ADIYLA reddedilir; başarılı her değişim denetime yazılır.
 */
final class UpdateBillingModeController extends Controller
{
    public function __construct(private readonly ManageBillingMode $mode) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'mode' => ['required', 'string', Rule::in(['sandbox', 'live'])],
        ])->validate();

        try {
            $status = $this->mode->request(BillingMode::from((string) $validated['mode']), (int) $request->user()->getKey());
        } catch (BillingModeRejectedException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'reason' => $exception->reason], 422);
        }

        return response()->json($status->toArray());
    }
}
