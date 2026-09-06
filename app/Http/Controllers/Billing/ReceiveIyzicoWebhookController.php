<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Application\Billing\UseCase\ManageCheckout;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReceiveIyzicoWebhookController extends Controller
{
    public function __construct(
        private readonly ManageCheckout $checkout,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $signature = $request->header('X-IYZ-SIGNATURE-V3');

        $status = $this->checkout->receiveWebhook($request->json()->all(), is_string($signature) ? $signature : null);

        return response()->json([], $status);
    }
}
