<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Rendering;

use App\Application\Billing\Dto\Invoice;
use App\Application\Billing\Port\InvoiceDocumentRendererPort;
use App\Support\Billing\InvoiceDocumentHtml;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use RuntimeException;
use Throwable;

/**
 * A4 fatura çıktısı — QR baskı sayfasıyla aynı mPDF yolu.
 *
 * Sahip belgeyi indirir, muhasebecisine yollar ya da basar. Ekran kipi
 * paneldeki listedir; burası kâğıt kipidir (docs/130 §K6).
 */
final class MpdfInvoiceDocumentAdapter implements InvoiceDocumentRendererPort
{
    public function render(Invoice $invoice): string
    {
        $tempDir = storage_path('app/mpdf-tmp');

        if (! is_dir($tempDir) && ! @mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
            throw new RuntimeException("Unable to create mPDF temp directory: {$tempDir}");
        }

        try {
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'tempDir' => $tempDir,
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 15,
                'margin_bottom' => 15,
            ]);

            $mpdf->WriteHTML(InvoiceDocumentHtml::build($invoice));

            return (string) $mpdf->Output('', 'S');
        } catch (Throwable $exception) {
            throw new RuntimeException('Invoice document generation failed.', 0, $exception instanceof MpdfException ? $exception : null);
        }
    }
}
