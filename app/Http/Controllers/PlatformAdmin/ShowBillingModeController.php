<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Billing\UseCase\ManageBillingMode;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class ShowBillingModeController extends Controller
{
    public function __construct(private readonly ManageBillingMode $mode) {}

    public function __invoke(): JsonResponse
    {
        return response()->json($this->mode->status()->toArray());
    }
}
