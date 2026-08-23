<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Application\Billing\UseCase\ManageIyzicoSandboxCheckout;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReceiveIyzicoSandboxWebhookController extends Controller
{
    public function __construct(
        private readonly ManageIyzicoSandboxCheckout $checkout,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $signature = $request->header('X-IYZ-SIGNATURE-V3');

        $status = $this->checkout->receiveWebhook($payload, is_string($signature) ? $signature : null);

        return response()->json([], $status);
    }
}
