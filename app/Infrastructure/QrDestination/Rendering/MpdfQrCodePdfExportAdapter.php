<?php

declare(strict_types=1);

namespace App\Infrastructure\QrDestination\Rendering;

use App\Application\QrDestination\Dto\QrRenderedImage;
use App\Application\QrDestination\Port\QrCodePdfExportPort;
use App\Domain\QrDestination\QrLayout;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use RuntimeException;
use Throwable;

final class MpdfQrCodePdfExportAdapter implements QrCodePdfExportPort
{
    private const string FIXED_PDF_TIMESTAMP = "20250101000000+00'00'";

    private const string FIXED_PDF_ID = '00000000000000000000000000000000';

    /**
     * ISO 216 mm width/height per paper size, frozen by the MASTER contract.
     *
     * @var array<string, array{0: float, 1: float}>
     */
    private const array ISO_MM_SIZES = [
        'A4' => [210.0, 297.0],
        'B4' => [250.0, 353.0],
        'A5' => [148.0, 210.0],
        'B5' => [176.0, 250.0],
        'A6' => [105.0, 148.0],
        'B6' => [125.0, 176.0],
        'A7' => [74.0, 105.0],
        'B7' => [88.0, 125.0],
    ];

    public function renderPdf(string $pngBytes, string $token, ?string $paperSize = null, ?string $orientation = null, ?QrLayout $layout = null): QrRenderedImage
    {
        [$widthMm, $heightMm] = $this->resolveDimensionsMm($paperSize, $orientation);

        $tempDir = storage_path('app/mpdf-tmp');

        if (! is_dir($tempDir) && ! @mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
            throw new RuntimeException("Unable to create mPDF temp directory: {$tempDir}");
        }

        try {
            $mpdf = new Mpdf([
                'mode' => 'c',
                'format' => [$widthMm, $heightMm],
                'default_font' => 'helvetica',
                'tempDir' => $tempDir,
                'margin_left' => 0,
                'margin_right' => 0,
                'margin_top' => 0,
                'margin_bottom' => 0,
                'margin_header' => 0,
                'margin_footer' => 0,
            ]);

            $mpdf->WriteHTML($this->fixedHtml($pngBytes, $widthMm, $heightMm, $layout));

            $bytes = $mpdf->Output('', 'S');
        } catch (Throwable $exception) {
            throw new RuntimeException('QR PDF generation failed.', 0, $exception instanceof MpdfException ? $exception : null);
        }

        return new QrRenderedImage(
            bytes: $this->stabilizeIdentifiers($bytes),
            mimeType: 'application/pdf',
        );
    }

    /**
     * @return array{0: float, 1: float} [widthMm, heightMm]
     */
    private function resolveDimensionsMm(?string $paperSize, ?string $orientation): array
    {
        [$widthMm, $heightMm] = self::ISO_MM_SIZES[$paperSize] ?? self::ISO_MM_SIZES['A4'];

        if ($orientation === 'landscape') {
            return [$heightMm, $widthMm];
        }

        return [$widthMm, $heightMm];
    }

    private function fixedHtml(string $pngBytes, float $widthMm, float $heightMm, ?QrLayout $layout): string
    {
        $dataUri = 'data:image/png;base64,'.base64_encode($pngBytes);

        $qrSizeMm = min($widthMm, $heightMm) * 0.55;

        $backgroundCss = $layout !== null ? "background-color:#{$layout->backgroundRgb};" : '';

        return '<!DOCTYPE html><html><head><style>'
            .'html,body{margin:0;padding:0;}'
            ."body{width:{$widthMm}mm;height:{$heightMm}mm;display:table;{$backgroundCss}}"
            .'.cell{display:table-cell;vertical-align:middle;text-align:center;}'
            ."img{width:{$qrSizeMm}mm;height:{$qrSizeMm}mm;}"
            .'</style></head><body><div class="cell"><img src="'.$dataUri.'" alt=""></div></body></html>';
    }

    /**
     * The same input must always produce byte-identical output, but mPDF
     * stamps /CreationDate, /ModDate and the trailer /ID from time() and are
     * never exposed as configurable fields. Both replaced values are fixed
     * to the exact character length mPDF always emits (a constant-width PDF
     * date string, and a 32-hex-char id), so this cannot shift any other
     * object's byte offset in the already-written xref table.
     */
    private function stabilizeIdentifiers(string $pdf): string
    {
        $pdf = preg_replace(
            '/\/(CreationDate|ModDate) \(D:\d{14}[+\-Z][0-9\']{0,6}\)/',
            '/$1 (D:'.self::FIXED_PDF_TIMESTAMP.')',
            $pdf
        ) ?? $pdf;

        return preg_replace(
            '/\/ID \[<[0-9a-fA-F]{32}> <[0-9a-fA-F]{32}>\]/',
            '/ID [<'.self::FIXED_PDF_ID.'> <'.self::FIXED_PDF_ID.'>]',
            $pdf
        ) ?? $pdf;
    }
}
