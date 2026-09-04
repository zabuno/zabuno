<?php

declare(strict_types=1);

namespace App\Infrastructure\QrDestination\Rendering;

use App\Application\QrDestination\Dto\QrRenderedImage;
use App\Application\QrDestination\Port\QrPrintSheetPort;
use App\Domain\QrDestination\QrPrintSheet;
use App\Support\QrDestination\QrPrintSheetHtml;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use RuntimeException;
use Throwable;

final class MpdfQrPrintSheetAdapter implements QrPrintSheetPort
{
    private const string FIXED_PDF_TIMESTAMP = "20250101000000+00'00'";

    private const string FIXED_PDF_ID = '00000000000000000000000000000000';

    public function renderSheet(array $cards, string $caption, string $brandName): QrRenderedImage
    {
        $tempDir = storage_path('app/mpdf-tmp');

        if (! is_dir($tempDir) && ! @mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
            throw new RuntimeException("Unable to create mPDF temp directory: {$tempDir}");
        }

        try {
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => [QrPrintSheet::PAGE_WIDTH_MM, QrPrintSheet::PAGE_HEIGHT_MM],
                'tempDir' => $tempDir,
                'margin_left' => 0,
                'margin_right' => 0,
                'margin_top' => 0,
                'margin_bottom' => 0,
                'margin_header' => 0,
                'margin_footer' => 0,
            ]);

            $mpdf->WriteHTML(QrPrintSheetHtml::build($cards, $caption, $brandName));

            $bytes = $mpdf->Output('', 'S');
        } catch (Throwable $exception) {
            throw new RuntimeException('QR print sheet generation failed.', 0, $exception instanceof MpdfException ? $exception : null);
        }

        return new QrRenderedImage(
            bytes: $this->stabilizeIdentifiers($bytes),
            mimeType: 'application/pdf',
        );
    }

    /**
     * Aynı girdi her zaman aynı baytları vermeli; mPDF ise /CreationDate,
     * /ModDate ve /ID alanlarını `time()` ile damgalar ve bunları
     * yapılandırılabilir bir alan olarak açmaz. İkisi de mPDF'in her zaman
     * yazdığı sabit karakter uzunluğuyla değiştirilir, böylece yazılmış xref
     * tablosundaki hiçbir nesnenin bayt konumu kaymaz.
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
