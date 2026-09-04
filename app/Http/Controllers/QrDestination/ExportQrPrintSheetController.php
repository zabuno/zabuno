<?php

declare(strict_types=1);

namespace App\Http\Controllers\QrDestination;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Application\Publication\Port\PublicMenuAddressPort;
use App\Application\QrDestination\Dto\QrCodeRecord;
use App\Application\QrDestination\Dto\QrPrintCard;
use App\Application\QrDestination\Port\QrCodeImageExportPort;
use App\Application\QrDestination\Port\QrCodeRepositoryPort;
use App\Application\QrDestination\Port\QrPrintSheetPort;
use App\Domain\Authorization\Permission;
use App\Domain\QrDestination\QrPrintSheet;
use App\Domain\QrDestination\QrTheme;
use App\Http\Controllers\Controller;
use App\Support\Localization\GuestText;
use App\Support\QrDestination\QrLayoutResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Basılabilir sayfa — `docs/104` Döngü 8.
 *
 * Bugüne kadar tek çıktı vardı: A4'ün ortasında tek bir çıplak kare. 40 masalı
 * bir restoran için bu, 40 ayrı A4 demekti; her biri %97 beyaz ve baskıdan
 * sonra birbirinden ayırt edilemez. Sahip kartları masalara dağıtırken
 * hangisinin hangi masa olduğunu bilemiyordu — yani ürünün asıl çıktısı
 * kullanılamıyordu.
 *
 * Bu uç nokta bir DESTE üretir: sayfa başına on iki kart, her kartta restoran
 * adı, 40 mm karekod, masa adı, alan etiketi ve misafire hitap eden bir cümle;
 * her kartın çevresinde kesme çizgisi.
 */
final class ExportQrPrintSheetController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly MenuCatalogApiContextPort $context,
        private readonly QrCodeRepositoryPort $qrCodes,
        private readonly QrCodeImageExportPort $imageExport,
        private readonly QrPrintSheetPort $printSheet,
        private readonly PublicMenuAddressPort $addresses,
        private readonly GuestText $guestText,
        private readonly QrLayoutResolver $layouts,
    ) {}

    public function __invoke(Request $request, int $workspace, int $location): Response
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::QrView, $workspace)) {
            return response('Not Found.', 404);
        }

        if ($this->context->locationWorkspaceId($location) !== $workspace) {
            return response('Not Found.', 404);
        }

        if (! $this->authorization->can($userId, Permission::QrDesignManage, $workspace)) {
            return response('Forbidden.', 403);
        }

        $validated = $request->validate([
            'theme' => ['sometimes', Rule::in(array_map(static fn (QrTheme $case) => $case->value, QrTheme::cases()))],
            'chunk' => ['sometimes', 'integer', 'min:1'],
        ]);

        $chunk = (int) ($validated['chunk'] ?? 1);

        /*
            YALNIZ ETKİN KODLAR basılır. Kapatılmış bir kodu kâğıda dökmek,
            sahibi kendi eliyle ölü bir kart bastırmaya davet etmek olurdu.
        */
        $active = array_values(array_filter(
            $this->qrCodes->listForLocation($workspace, $location),
            static fn (QrCodeRecord $record): bool => $record->state === 'active',
        ));

        if ($active === []) {
            return response('Not Found.', 404);
        }

        $slice = array_slice(
            $active,
            ($chunk - 1) * QrPrintSheet::CARDS_PER_REQUEST,
            QrPrintSheet::CARDS_PER_REQUEST,
        );

        if ($slice === []) {
            return response('Not Found.', 404);
        }

        /*
            Kartın dili RESTORANIN dilidir, uygulamanınkinin değil: kartı
            okuyan kişi masadadır ve menü zaten restoranın dilindedir. Marka
            adı ve dil, yayınlanmış menünün adresinden gelir; menü henüz
            yayınlanmadıysa ikisi de boştur ve uydurulmaz.
        */
        $address = $this->addresses->findByQrToken($slice[0]->token);
        $brandName = $address['brand_name'] ?? '';
        $caption = $this->guestText->get('guest.print.scanForMenu', $address['locale'] ?? null);

        $layout = $this->layouts->resolve($validated['theme'] ?? null, $workspace, $slice[0]->menuId);

        try {
            $cards = array_map(
                fn (QrCodeRecord $record): QrPrintCard => new QrPrintCard(
                    pngBytes: $this->imageExport->renderPng(url("/q/{$record->token}"), $layout)->bytes,
                    title: $record->tableName ?? $record->token,
                    subtitle: $record->areaLabel ?? '',
                ),
                $slice,
            );

            $rendered = $this->printSheet->renderSheet($cards, $caption, $brandName);
        } catch (RuntimeException $exception) {
            report($exception);

            return response('QR print sheet generation failed.', 500);
        }

        return response($rendered->bytes, 200, [
            'Content-Type' => $rendered->mimeType,
            'Content-Disposition' => 'attachment; filename="qr-print-sheet-'.$chunk.'.pdf"',
            /*
                Kaç kart basıldığı ve kaç kart kaldığı BAŞLIKTA söylenir:
                istemci bunu ikinci bir listeleme isteği atmadan bilir ve
                "3 sayfadan 1." gibi bir cümleyi dürüstçe kurabilir.
            */
            'X-Qr-Sheet-Cards' => (string) count($slice),
            'X-Qr-Sheet-Total' => (string) count($active),
        ]);
    }
}
