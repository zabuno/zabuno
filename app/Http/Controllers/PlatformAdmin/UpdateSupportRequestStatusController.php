<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Support\UseCase\ChangeSupportRequestStatus;
use App\Domain\Support\SupportRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlatformAdmin\UpdateSupportRequestStatusRequest;
use Illuminate\Http\JsonResponse;

final class UpdateSupportRequestStatusController extends Controller
{
    public function __construct(
        private readonly ChangeSupportRequestStatus $changeStatus,
    ) {}

    public function __invoke(UpdateSupportRequestStatusRequest $request, int $supportRequest): JsonResponse
    {
        $summary = $this->changeStatus->handle(
            $supportRequest,
            SupportRequestStatus::from((string) $request->validated('status')),
        );

        if ($summary === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json($summary->toArray());
    }
}
