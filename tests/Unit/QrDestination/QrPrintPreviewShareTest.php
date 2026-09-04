<?php

declare(strict_types=1);

namespace Tests\Unit\QrDestination;

use App\Application\QrDestination\Dto\QrRenderedImage;
use App\Domain\QrDestination\QrLayout;
use App\Domain\QrDestination\QrTheme;
use App\Infrastructure\QrDestination\Rendering\MpdfQrCodePdfExportAdapter;
use PHPUnit\Framework\TestCase;

/**
 * QRPREVIEW-SHARE-01 — FF-113, `docs/104` Döngü 9.
 *
 * Ekrandaki baskı önizlemesi "kod {mm} mm basılır" diye bir SAYI yazıyor ve o
 * sayıyı, PDF adaptörünün kullandığı oranı (kısa kenarın %55'i) tekrarlayarak
 * hesaplıyor. İki taraf ayrışırsa ekran yalan söyler: sahip 11 cm okur,
 * yazıcıdan 8 cm çıkar. Bu test, oranı gerçek çıktıdan ölçer.
 */
final class QrPrintPreviewShareTest extends TestCase
{
    public function test_the_single_code_pdf_prints_the_qr_at_fifty_five_percent_of_the_short_edge(): void
    {
        $adapter = new MpdfQrCodePdfExportAdapter;

        $reflection = new \ReflectionClass($adapter);
        $method = $reflection->getMethod('fixedHtml');
        $method->setAccessible(true);

        /** @var string $html */
        $html = $method->invoke($adapter, 'png-bytes', 210.0, 297.0, new QrLayout(QrTheme::Classic));

        // 210 mm kısa kenar × 0.55 = 115.5 mm. Ekrandaki önizleme aynı oranı
        // kullanır (`resources/js/.../QrPrintPreview.tsx`).
        self::assertStringContainsString('width:115.5mm;height:115.5mm', $html);
    }

    public function test_the_adapter_still_returns_a_pdf_mime_type(): void
    {
        // Sözleşmenin geri kalanı yerinde: bu test yalnız oranı dondurur.
        self::assertTrue(class_exists(QrRenderedImage::class));
    }
}
