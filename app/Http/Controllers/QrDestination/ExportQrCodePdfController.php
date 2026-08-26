<?php

declare(strict_types=1);

namespace App\Http\Controllers\QrDestination;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\QrDestination\Port\QrCodeImageExportPort;
use App\Application\QrDestination\Port\QrCodePdfExportPort;
use App\Application\QrDestination\Port\QrCodeRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Domain\QrDestination\QrLayout;
use App\Domain\QrDestination\QrTheme;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use RuntimeException;

final class ExportQrCodePdfController extends Controller
{
    private const array PAPER_SIZES = ['A4', 'B4', 'A5', 'B5', 'A6', 'B6', 'A7', 'B7'];

    private const array ORIENTATIONS = ['portrait', 'landscape'];

    public function __construct(
        private readonly AuthorizationPort $authorization,
        private readonly QrCodeRepositoryPort $qrCodes,
        private readonly QrCodeImageExportPort $imageExport,
        private readonly QrCodePdfExportPort $pdfExport,
    ) {}

    public function __invoke(Request $request, int $workspace, int $qrCode): Response
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
            'paperSize' => ['sometimes', Rule::in(self::PAPER_SIZES)],
            'orientation' => ['sometimes', Rule::in(self::ORIENTATIONS)],
            'theme' => ['sometimes', Rule::in(array_map(static fn (QrTheme $case) => $case->value, QrTheme::cases()))],
        ]);

        $paperSize = $validated['paperSize'] ?? 'A4';
        $orientation = $validated['orientation'] ?? 'portrait';
        $layout = new QrLayout(QrTheme::from($validated['theme'] ?? QrTheme::Classic->value));

        try {
            $png = $this->imageExport->renderPng(url("/q/{$record->token}"), $layout);
            $rendered = $this->pdfExport->renderPdf($png->bytes, $record->token, $paperSize, $orientation, $layout);
        } catch (RuntimeException $exception) {
            report($exception);

            return response('QR PDF generation failed.', 500);
        }

        $headers = ['Content-Type' => $rendered->mimeType];

        if ($request->boolean('download')) {
            $headers['Content-Disposition'] = 'attachment; filename="qr-'.$record->token.'.pdf"';
        }

        return response($rendered->bytes, 200, $headers);
    }
}
