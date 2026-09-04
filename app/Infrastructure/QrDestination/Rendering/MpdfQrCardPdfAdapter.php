<?php

declare(strict_types=1);

namespace App\Infrastructure\QrDestination\Rendering;

use App\Application\QrDestination\Dto\QrRenderedImage;
use App\Application\QrDestination\Port\QrCardExportPort;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use RuntimeException;
use Throwable;

final class MpdfQrCardPdfAdapter implements QrCardExportPort
{
    private const string FIXED_PDF_TIMESTAMP = "20250101000000+00'00'";

    private const string FIXED_PDF_ID = '00000000000000000000000000000000';

    public function renderCardPdf(string $cardSvg, float $widthMm, float $heightMm): QrRenderedImage
    {
        $tempDir = storage_path('app/mpdf-tmp');

        if (! is_dir($tempDir) && ! @mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
            throw new RuntimeException("Unable to create mPDF temp directory: {$tempDir}");
        }

        try {
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                // Sayfa KARTIN TA KENDİSİ kadardır: kart bir A4'ün ortasına
                // konmaz, kesilecek kâğıdın kendisi odur.
                'format' => [$widthMm, $heightMm],
                'tempDir' => $tempDir,
                'margin_left' => 0,
                'margin_right' => 0,
                'margin_top' => 0,
                'margin_bottom' => 0,
                'margin_header' => 0,
                'margin_footer' => 0,
            ]);

            /*
                SVG bir GÖRSEL olarak gömülür. mPDF'in SVG ayrıştırıcısı yol ve
                dikdörtgenleri doğru çiziyor; metin ise sola hizalı yazıldığı
                için `text-anchor` desteğine bağımlı değil (bkz. `QrCardSvg`
                sınıf başlığı — bu karar üretilen PDF'e gözle bakılarak
                alındı).
            */
            $mpdf->WriteHTML(
                '<html><body style="margin:0"><img src="data:image/svg+xml;base64,'
                .base64_encode($cardSvg)
                .'" style="width:'.$widthMm.'mm;height:'.$heightMm.'mm"></body></html>'
            );

            $bytes = $mpdf->Output('', 'S');
        } catch (Throwable $exception) {
            throw new RuntimeException('QR card PDF generation failed.', 0, $exception instanceof MpdfException ? $exception : null);
        }

        return new QrRenderedImage(
            bytes: $this->stabilizeIdentifiers($bytes),
            mimeType: 'application/pdf',
        );
    }

    /** Aynı girdi her zaman aynı baytları vermeli; mPDF tarih ve kimlik damgalar. */
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
