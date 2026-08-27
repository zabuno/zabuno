<?php

declare(strict_types=1);

namespace App\Http\Controllers\QrDestination;

use App\Application\Analytics\UseCase\RecordAnalyticsEvent;
use App\Application\Publication\Port\PublicationRepositoryPort;
use App\Application\Publication\Port\PublicMenuAddressPort;
use App\Application\QrDestination\Port\QrCodeRepositoryPort;
use App\Domain\Analytics\AnalyticsEventType;
use App\Domain\Publication\MenuPublicAddress;
use App\Domain\QrDestination\QrToken;
use App\Domain\Url\CanonicalUrl;
use App\Http\Controllers\Controller;
use App\Http\Responses\GuestDeadEnd;
use App\Support\Seo\MenuStructuredData;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class ShowPublicMenuController extends Controller
{
    public function __construct(
        private readonly QrCodeRepositoryPort $qrCodes,
        private readonly PublicationRepositoryPort $publications,
        private readonly RecordAnalyticsEvent $recordAnalyticsEvent,
        private readonly CanonicalUrl $canonical,
        private readonly PublicMenuAddressPort $addresses,
    ) {}

    public function __invoke(Request $request, string $token): SymfonyResponse
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

        $address = $this->addresses->findByQrToken($qrToken->value());

        if ($address === null) {
            return $this->notFound();
        }

        $this->recordAnalyticsEvent->handle(
            $record->workspaceId,
            $record->locationId,
            $record->id,
            $record->menuId,
            AnalyticsEventType::MenuOpen,
        );

        // Sayfa burada RENDER EDİLİR (yönlendirilmez): misafirin karekodu
        // taradıktan sonra bir sıçrama daha beklemesi için sebep yok ve
        // huninin ikinci yarısı burada ölçülür.
        //
        // Ama KANONİK adres bu değildir: arama motoruna menünün kalıcı
        // adresi gösterilir ve bu sayfa indekslenmez. Böylece token hiçbir
        // zaman sitemap'e girmez ve `/q/` için koyulan hız sınırı anlamlı
        // kalır (`docs/38` §21).
        $canonicalPath = MenuPublicAddress::fromKeyAndSlug($address['key'], $address['slug'])->path();

        return response()->view('public-menu', [
            'snapshot' => $publication->snapshot,
            'analyticsContext' => [
                'zabuno_surface' => 'menu',
                'zabuno_tenant_id' => (string) $record->workspaceId,
                'zabuno_location_id' => (string) $record->locationId,
                'zabuno_menu_id' => (string) $record->menuId,
            ],
            'canonicalUrl' => $canonicalUrl = $this->canonical->for($request->getSchemeAndHttpHost(), $canonicalPath),
            'contentLocale' => $address['locale'] !== '' ? $address['locale'] : null,
            'structuredData' => json_encode(
                MenuStructuredData::forMenu($publication->snapshot, $canonicalUrl, $address['brand_name']),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP,
            ),
        ], 200)->header('X-Robots-Tag', 'noindex, follow');
    }

    private function notFound(): SymfonyResponse
    {
        // Tarayıcıda ham JSON gören bir misafir, ürünü bozuk sanır.
        // Yanıt her durumda aynıdır (QR-PUBLIC-404-UNIFORM-01).
        return GuestDeadEnd::respond(request());
    }
}
