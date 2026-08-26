<?php

declare(strict_types=1);

namespace App\Http\Controllers\QrDestination;

use App\Application\Analytics\UseCase\RecordAnalyticsEvent;
use App\Application\Publication\Port\PublicationRepositoryPort;
use App\Application\QrDestination\Port\QrCodeRepositoryPort;
use App\Domain\Analytics\AnalyticsEventType;
use App\Domain\QrDestination\QrToken;
use App\Domain\Url\CanonicalUrl;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use InvalidArgumentException;

final class ShowPublicMenuController extends Controller
{
    public function __construct(
        private readonly QrCodeRepositoryPort $qrCodes,
        private readonly PublicationRepositoryPort $publications,
        private readonly RecordAnalyticsEvent $recordAnalyticsEvent,
        private readonly CanonicalUrl $canonical,
    ) {}

    public function __invoke(string $token): Response|JsonResponse
    {
        try {
            $qrToken = QrToken::fromString($token);
        } catch (InvalidArgumentException) {
            return $this->notFound();
        }

        $record = $this->qrCodes->findActiveByToken($qrToken->value());

        if ($record === null) {
            return $this->notFound();
        }

        $publication = $this->publications->current($record->workspaceId, $record->menuId);

        if ($publication === null) {
            return $this->notFound();
        }

        $this->recordAnalyticsEvent->handle(
            $record->workspaceId,
            $record->locationId,
            $record->id,
            $record->menuId,
            AnalyticsEventType::MenuOpen,
        );

        // Kanonik adres sunucuda üretilir. İstemcide üretilseydi, JavaScript
        // çalıştırmayan tarama/önizleme botları onu hiç görmezdi — ve bu
        // sayfayı paylaşan çoğu araç JavaScript çalıştırmaz.
        return response()->view('public-menu', [
            'snapshot' => $publication->snapshot,
            'canonicalUrl' => $this->canonical->for(
                request()->getSchemeAndHttpHost(),
                '/menu/'.$qrToken->value(),
            ),
        ], 200);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => 'Not Found.'], 404);
    }
}
