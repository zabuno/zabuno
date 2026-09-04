<?php

declare(strict_types=1);

namespace App\Http\Controllers\QrDestination;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MenuMediaPort;
use App\Application\Publication\Port\MenuIdentityPort;
use App\Application\Publication\Port\PublicMenuAddressPort;
use App\Application\QrDestination\Port\QrCardExportPort;
use App\Application\QrDestination\Port\QrCodeImageExportPort;
use App\Application\QrDestination\Port\QrCodeRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Domain\QrDestination\CardOrientation;
use App\Domain\QrDestination\CardSize;
use App\Domain\QrDestination\CardTheme;
use App\Domain\QrDestination\QrLayout;
use App\Domain\QrDestination\QrTheme;
use App\Http\Controllers\Controller;
use App\Support\Localization\GuestText;
use App\Support\QrDestination\QrCardSvg;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * MASADAKİ KART — FF-120, sahibin talebi (2026-09-04).
 *
 * "Bir restoran yöneticisi olarak menümü masalarda pleksiglas içinde
 * göstermek istiyorum, bu nedenle printout alabilmeliyim. Fakat her
 * restoranın marka kimliği ayrı."
 *
 * Bu uç, tek bir kodun basılabilir kartını üretir. Tek kodun eski
 * `export.pdf` ucundan AYRIDIR ve ayrı olması gerekir: o, A4'ün ortasına
 * konan çıplak bir karedir (duvara asılacak afiş); bu ise kesilip
 * pleksiglasa girecek, marka kimliği taşıyan bir karttır.
 *
 * İki biçim sunulur ve ikisi de AYNI kaynaktan üretilir: SVG bestecinin
 * çıktısıdır, PDF o SVG'nin sayfaya oturtulmuş hâlidir. PNG SUNULMAZ ve bu
 * bir eksiklik değil bir karar: raster bir görsel 4 cm'lik bir karekodda
 * modül kenarlarını bulanıklaştırır, ve PNG'yi ayrı bir bestecinin çizmesi
 * gerekirdi — iki besteci bir gün iki farklı kart üretir.
 */
final class ExportQrCardController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly QrCodeRepositoryPort $qrCodes,
        private readonly QrCodeImageExportPort $imageExport,
        private readonly QrCardExportPort $cardExport,
        private readonly MenuIdentityPort $identities,
        private readonly MenuMediaPort $media,
        private readonly PublicMenuAddressPort $addresses,
        private readonly GuestText $guestText,
    ) {}

    public function __invoke(Request $request, int $workspace, int $qrCode, string $format): Response
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::QrView, $workspace)) {
            return response('Not Found.', 404);
        }

        $record = $this->qrCodes->findById($qrCode);

        if ($record === null || $record->workspaceId !== $workspace || $record->state !== 'active') {
            return response('Not Found.', 404);
        }

        if (! $this->authorization->can($userId, Permission::QrDesignManage, $workspace)) {
            return response('Forbidden.', 403);
        }

        $validated = $request->validate([
            'cardTheme' => ['sometimes', Rule::in(array_map(static fn (CardTheme $c) => $c->value, CardTheme::cases()))],
            'size' => ['sometimes', Rule::in(array_map(static fn (CardSize $c) => $c->value, CardSize::cases()))],
            'orientation' => ['sometimes', Rule::in(array_map(static fn (CardOrientation $c) => $c->value, CardOrientation::cases()))],
            // Sahip kendi cümlesini yazabilir; yazmazsa misafir alanındaki
            // hazır cümle kullanılır.
            'headline' => ['sometimes', 'string', 'max:60'],
        ]);

        $theme = CardTheme::from($validated['cardTheme'] ?? CardTheme::Classic->value);
        $size = CardSize::from($validated['size'] ?? CardSize::A6->value);
        $orientation = CardOrientation::from($validated['orientation'] ?? CardOrientation::Portrait->value);

        $identity = $this->identities->forMenu($workspace, $record->menuId);
        $brandName = $identity?->brandName ?? '';

        $headline = trim((string) ($validated['headline'] ?? ''));

        if ($headline === '') {
            /*
                Kartı okuyan kişi MASADADIR ve menü restoranın dilindedir;
                cümle de o dilde yazılır. Dil markanın kendi ayarından gelir —
                aynı kaynak baskı sayfasında da kullanılıyor, iki yüzey aynı
                cümleyi aynı dilde yazsın diye.
            */
            $address = $this->addresses->findByQrToken($record->token);
            $headline = $this->guestText->get('guest.print.scanForMenu', $address['locale'] ?? null);
        }

        /*
            LOGO KARTA GÖMÜLÜR (FF-124). Genişlik isteği kartın gerçek
            ölçüsünden türer: 2 cm'lik bir logo için 2000 piksellik dosyayı
            gömmek, arşivi gereksiz yere şişirirdi.
        */
        $logo = $this->brandLogo($workspace, $record->menuId);

        try {
            $qrSvg = $this->imageExport->renderSvg(
                url("/q/{$record->token}"),
                // Kodun kendisi HER ZAMAN klasik: taranabilirlik pazarlık
                // konusu değil. Marka rengi kartın çerçevesine uygulanır.
                new QrLayout(QrTheme::Classic),
            )->bytes;

            $cardSvg = QrCardSvg::compose(
                $qrSvg,
                $theme,
                $size,
                $orientation,
                $brandName,
                $headline,
                $identity?->primaryColor,
                $logo,
            );

            if ($format === 'svg') {
                return response($cardSvg, 200, [
                    'Content-Type' => 'image/svg+xml',
                    'Content-Disposition' => $this->disposition($request, $record->token, 'svg'),
                ]);
            }

            [$width, $height] = $orientation->apply($size);
            $rendered = $this->cardExport->renderCardPdf($cardSvg, $width, $height);
        } catch (RuntimeException $exception) {
            report($exception);

            return response('QR card generation failed.', 500);
        }

        return response($rendered->bytes, 200, [
            'Content-Type' => $rendered->mimeType,
            'Content-Disposition' => $this->disposition($request, $record->token, 'pdf'),
        ]);
    }

    /**
     * Markanın logosu, karta gömülebilecek hâlde.
     *
     * @return array{bytes: string, mimeType: string}|null
     */
    private function brandLogo(int $workspace, int $menuId): ?array
    {
        $brandId = $this->identities->brandIdForMenu($workspace, $menuId);

        if ($brandId === null) {
            return null;
        }

        // Kartta logo yaklaşık 2 cm; 300 DPI'da ~240 piksel yeter.
        return $this->media->brandLogoBytes($workspace, $brandId, 240);
    }

    private function disposition(Request $request, string $token, string $extension): string
    {
        // Önizleme için satır içi, indirme için ek. Aynı adres iki iş görür ve
        // istemcinin ikinci bir uç öğrenmesi gerekmez.
        $kind = $request->boolean('download') ? 'attachment' : 'inline';

        return $kind.'; filename="zabuno-kart-'.$token.'.'.$extension.'"';
    }
}
