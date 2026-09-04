import { t } from '../../../../i18n/workspace';
import type { QrOrientation, QrPaperSize } from './QrExportConfigForm';

/**
 * YAZICIDAN NE ÇIKACAK — `docs/104` Döngü 9.
 *
 * Kâğıt boyu ve yön açılır listeleri, kontrol ettikleri sonucu hiçbir yerde
 * göstermiyordu: sahip "A6 yatay" seçiyor ve ne olacağını ancak yazıcıdan
 * kâğıt çıkınca öğreniyordu. Yazdırma deneyiminin temel kuralı, ayarların bir
 * ÖNİZLEMEYE bağlı olmasıdır (Microsoft UX Guide).
 *
 * Önizleme bir resim değil, bir ŞEMADIR: kâğıdın oranı ve kodun o kâğıt
 * içindeki gerçek payı ölçekli çizilir. Bir raster önizleme burada yanıltıcı
 * olurdu — ekranda 200 piksel görünen bir kare, kâğıtta 11 cm'dir ve asıl
 * bilgi budur.
 *
 * Ekranda YAZAN sayı milimetredir ve yanında okuma mesafesi durur (10:1
 * kuralı: kod genişliği ≈ okuma mesafesinin onda biri). "A4 dikey" bir
 * restoran sahibine hiçbir şey anlatmaz; "11 cm kod, ~1,1 m'den okunur"
 * kartın nereye asılacağını anlatır.
 */

/** ISO 216 mm ölçüleri — `MpdfQrCodePdfExportAdapter::ISO_MM_SIZES` ile aynı. */
const PAPER_MM: Record<QrPaperSize, [number, number]> = {
    A4: [210, 297],
    B4: [250, 353],
    A5: [148, 210],
    B5: [176, 250],
    A6: [105, 148],
    B6: [125, 176],
    A7: [74, 105],
    B7: [88, 125],
};

/**
 * Tek kodun PDF'inde karekod, kâğıdın kısa kenarının %55'i kadar basılır
 * (`MpdfQrCodePdfExportAdapter`). Sayı burada TEKRARLANMAZ diye bir kural
 * yazamayız — sunucu ile istemci arasında paylaşılan bir ölçü yok — ama
 * kaynağı adıyla anılır ve testi ikisinin aynı kalmasını ölçer.
 */
const QR_SHARE_OF_SHORT_EDGE = 0.55;

export type QrPrintPreviewProps = {
    paperSize: QrPaperSize;
    orientation: QrOrientation;
};

function paperMm(paperSize: QrPaperSize, orientation: QrOrientation): [number, number] {
    const [width, height] = PAPER_MM[paperSize];

    return orientation === 'Landscape' ? [height, width] : [width, height];
}

export function qrPrintedSizeMm(paperSize: QrPaperSize, orientation: QrOrientation): number {
    const [width, height] = paperMm(paperSize, orientation);

    return Math.round(Math.min(width, height) * QR_SHARE_OF_SHORT_EDGE);
}

export function QrPrintPreview({ paperSize, orientation }: QrPrintPreviewProps) {
    const [widthMm, heightMm] = paperMm(paperSize, orientation);
    const qrMm = qrPrintedSizeMm(paperSize, orientation);

    // Şema, en uzun kenarı sabit bir kutuya oturtularak ölçeklenir; oran
    // korunur, böylece "yatay" gerçekten yatay görünür.
    const scale = 12 / Math.max(widthMm, heightMm);

    return (
        <figure className="flex flex-wrap items-center gap-[var(--space-4)]">
            <span
                aria-hidden="true"
                className="flex items-center justify-center border border-dashed border-border bg-white"
                style={{
                    width: `${String(widthMm * scale)}rem`,
                    height: `${String(heightMm * scale)}rem`,
                }}
            >
                <span
                    className="bg-[var(--color-fg)]"
                    style={{
                        width: `${String(qrMm * scale)}rem`,
                        height: `${String(qrMm * scale)}rem`,
                    }}
                />
            </span>

            <figcaption className="flex flex-col gap-[var(--space-1)] text-meta text-fg-secondary">
                <span className="font-semibold text-fg">
                    {t('workspace.publication.qrExport.preview.paper', {
                        paper: paperSize,
                        width: String(widthMm),
                        height: String(heightMm),
                    })}
                </span>
                {/*
                    10:1 KURALI. Kod genişliği ≈ okuma mesafesinin onda biri.
                    Mesafeyi yazmak, "A4 dikey" cümlesinin yapamadığı işi
                    yapar: sahip kartı nereye koyacağını anlar.
                */}
                <span>
                    {t('workspace.publication.qrExport.preview.size', {
                        mm: String(qrMm),
                        distance: String(Math.round(qrMm / 10)),
                    })}
                </span>
            </figcaption>
        </figure>
    );
}

export default QrPrintPreview;
