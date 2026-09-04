<?php

declare(strict_types=1);

namespace App\Http\Controllers\QrDestination;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\Media\Port\MenuMediaPort;
use App\Application\MenuCatalog\Api\Port\MenuCatalogApiContextPort;
use App\Application\Publication\Port\MenuIdentityPort;
use App\Application\Publication\Port\PublicMenuAddressPort;
use App\Application\QrDestination\Dto\QrCodeRecord;
use App\Application\QrDestination\Port\QrCardExportPort;
use App\Application\QrDestination\Port\QrCodeImageExportPort;
use App\Application\QrDestination\Port\QrCodeRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Domain\Publication\MenuPublicAddress;
use App\Domain\QrDestination\CardOrientation;
use App\Domain\QrDestination\CardSize;
use App\Domain\QrDestination\CardTheme;
use App\Domain\QrDestination\QrLayout;
use App\Domain\QrDestination\QrPrintSheet;
use App\Domain\QrDestination\QrTheme;
use App\Http\Controllers\Controller;
use App\Support\Localization\GuestText;
use App\Support\QrDestination\QrCardSvg;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use RuntimeException;
use ZipArchive;

/**
 * TOPLU KART — FF-122, sahibin talebi (2026-09-04):
 * "toplu QR oluşturabilmeli, toplu baskı için .zip export alabilirim."
 *
 * Deste PDF'inden (FF-111) AYRI bir iştir ve ayrı olması gerekir: o, evde
 * kesilecek bir tabakadır; bu ise matbaaya gider ve matbaa her kartı AYRI
 * dosya olarak ister. Dosya adı hangi masa olduğunu söylemek zorundadır —
 * yoksa kırk dosyayı açıp tek tek bakmak gerekir.
 *
 * ALANA GÖRE SÜZÜLEBİLİR ("salon üst kat, bahçe"): kırk masalık bir
 * restoranda bahçenin kartlarını yenilemek için kırk kartı birden basmak,
 * otuz kartı çöpe atmak demektir.
 */
final class ExportQrCardsZipController extends Controller
{
    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly MenuCatalogApiContextPort $context,
        private readonly QrCodeRepositoryPort $qrCodes,
        private readonly QrCodeImageExportPort $imageExport,
        private readonly QrCardExportPort $cardExport,
        private readonly MenuIdentityPort $identities,
        private readonly MenuMediaPort $media,
        private readonly PublicMenuAddressPort $addresses,
        private readonly GuestText $guestText,
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
            'cardTheme' => ['sometimes', Rule::in(array_map(static fn (CardTheme $c) => $c->value, CardTheme::cases()))],
            'size' => ['sometimes', Rule::in(array_map(static fn (CardSize $c) => $c->value, CardSize::cases()))],
            'orientation' => ['sometimes', Rule::in(array_map(static fn (CardOrientation $c) => $c->value, CardOrientation::cases()))],
            'format' => ['sometimes', Rule::in(['svg', 'pdf'])],
            'headline' => ['sometimes', 'string', 'max:60'],
            'areaId' => ['sometimes', 'integer'],
        ]);

        $theme = CardTheme::from($validated['cardTheme'] ?? CardTheme::Classic->value);
        $size = CardSize::from($validated['size'] ?? CardSize::A6->value);
        $orientation = CardOrientation::from($validated['orientation'] ?? CardOrientation::Portrait->value);
        $format = $validated['format'] ?? 'svg';
        $areaId = isset($validated['areaId']) ? (int) $validated['areaId'] : null;

        // Yalnız ETKİN kodlar: kapatılmış bir kodu kâğıda dökmek, sahibi kendi
        // eliyle ölü bir kart bastırmaya davet etmek olurdu.
        $active = array_values(array_filter(
            $this->qrCodes->listForLocation($workspace, $location),
            static fn (QrCodeRecord $record): bool => $record->state === 'active'
                && ($areaId === null || $record->areaId === $areaId),
        ));

        if ($active === []) {
            /*
                Boş bir ZIP indirmek kullanıcıya "oldu" demektir; olmadı.
                Tanınmayan bir alan kimliği de buraya düşer — "hepsini bas"
                demek, sahibe istemediği kırk kartı bastırmak olurdu.
            */
            return response('Not Found.', 404);
        }

        $slice = array_slice($active, 0, QrPrintSheet::CARDS_PER_REQUEST);

        $identity = $this->identities->forMenu($workspace, $slice[0]->menuId);
        $brandName = $identity?->brandName ?? '';

        $headline = trim((string) ($validated['headline'] ?? ''));

        if ($headline === '') {
            $address = $this->addresses->findByQrToken($slice[0]->token);
            $headline = $this->guestText->get('guest.print.scanForMenu', $address['locale'] ?? null);
        }

        [$width, $height] = $orientation->apply($size);

        // Logo BİR KEZ okunur ve her karta gömülür: kırk kart için kırk kez
        // diskten okumak, arşivi üretme süresini gereksiz uzatırdı.
        $brandId = $this->identities->brandIdForMenu($workspace, $slice[0]->menuId);
        $logo = $brandId === null ? null : $this->media->brandLogoBytes($workspace, $brandId, 240);

        $path = tempnam(sys_get_temp_dir(), 'zabuno-cards-');

        if ($path === false) {
            return response('QR card archive failed.', 500);
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);

            return response('QR card archive failed.', 500);
        }

        try {
            $used = [];

            foreach ($slice as $record) {
                $qrSvg = $this->imageExport->renderSvg(
                    url("/q/{$record->token}"),
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

                $bytes = $format === 'svg'
                    ? $cardSvg
                    : $this->cardExport->renderCardPdf($cardSvg, $width, $height)->bytes;

                $zip->addFromString($this->fileNameFor($record, $format, $used), $bytes);
            }
        } catch (RuntimeException $exception) {
            $zip->close();
            @unlink($path);
            report($exception);

            return response('QR card archive failed.', 500);
        }

        $zip->close();

        $bytes = (string) file_get_contents($path);
        @unlink($path);

        return response($bytes, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="zabuno-kartlar.zip"',
            // Kaç kart ve kaç kart kaldı: istemci ikinci bir istek atmadan
            // "48'den fazlası var" diyebilsin.
            'X-Qr-Cards-Count' => (string) count($slice),
            'X-Qr-Cards-Total' => (string) count($active),
        ]);
    }

    /**
     * Dosya adı HANGİ MASA olduğunu söyler.
     *
     * `qr-a3f9…svg` diye kırk dosya, matbaaya "hangisi hangisi" sorusunu
     * sordurur. Adı olmayan kod için token'ın ilk parçası kullanılır — ad
     * uydurulmaz. Aynı ad iki kez çıkarsa sıra numarası eklenir: ZIP içinde
     * aynı adlı ikinci dosya birincisini sessizce gölgelerdi.
     *
     * @param  array<string, int>  $used
     */
    private function fileNameFor(QrCodeRecord $record, string $format, array &$used): string
    {
        $base = MenuPublicAddress::slugFor((string) ($record->tableName ?? ''));

        if ($base === '') {
            $base = 'kart-'.substr($record->token, 0, 8);
        } elseif ($record->tableName !== null) {
            // Masa adı zaten kısa ve okunur; slug'lamak "T12"yi bozmaz ama
            // Türkçe harfleri dosya adında güvenli kılar.
            $base = $record->tableName;
        }

        $name = $base;
        $suffix = 1;

        while (isset($used[$name])) {
            $suffix++;
            $name = $base.'-'.$suffix;
        }

        $used[$name] = 1;

        return $name.'.'.$format;
    }
}
