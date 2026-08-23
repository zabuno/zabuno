<?php

declare(strict_types=1);

namespace App\Http\Controllers\QrDestination;

use App\Application\Analytics\UseCase\RecordAnalyticsEvent;
use App\Application\QrDestination\Port\QrCodeRepositoryPort;
use App\Domain\Analytics\AnalyticsEventType;
use App\Domain\QrDestination\QrToken;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

final class RedirectQrTokenController extends Controller
{
    public function __construct(
        private readonly QrCodeRepositoryPort $qrCodes,
        private readonly RecordAnalyticsEvent $recordAnalyticsEvent,
    ) {}

    public function __invoke(string $token): RedirectResponse|JsonResponse
    {
        try {
            $qrToken = QrToken::fromString($token);
        } catch (InvalidArgumentException) {
            return $this->notFound();
        }

        $qrCode = $this->qrCodes->findActiveByToken($qrToken->value());

        if ($qrCode === null) {
            return $this->notFound();
        }

        $this->recordAnalyticsEvent->handle(
            $qrCode->workspaceId,
            $qrCode->locationId,
            $qrCode->id,
            $qrCode->menuId,
            AnalyticsEventType::QrResolve,
        );

        return redirect("/menu/{$qrToken->value()}");
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => 'Not Found.'], 404);
    }
}
