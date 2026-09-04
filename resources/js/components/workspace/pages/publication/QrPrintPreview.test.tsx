import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';

import { QrPrintPreview, qrPrintedSizeMm } from './QrPrintPreview';

/**
 * QRPREVIEW-01 — FF-113, `docs/104` Döngü 9.
 *
 * Kâğıt boyu ve yön açılır listeleri, kontrol ettikleri sonucu hiçbir yerde
 * göstermiyordu: sahip "A6 yatay" seçiyor ve ne olacağını ancak yazıcıdan
 * kâğıt çıkınca öğreniyordu.
 */
describe('QrPrintPreview', () => {
    it('kâğıdın gerçek milimetresini ve kodun basılı boyunu YAZAR', () => {
        render(<QrPrintPreview paperSize="A4" orientation="Portrait" />);

        expect(screen.getByText('A4 — 210 × 297 mm')).toBeInTheDocument();
        // 210 × 0.55 = 115.5 → 116 mm; 10:1 kuralı ile ~12 cm.
        expect(screen.getByText(/prints 116 mm wide/i)).toBeInTheDocument();
        expect(screen.getByText(/about 12 cm away/i)).toBeInTheDocument();
    });

    it('yön gerçekten değişir', () => {
        render(<QrPrintPreview paperSize="A4" orientation="Landscape" />);

        expect(screen.getByText('A4 — 297 × 210 mm')).toBeInTheDocument();
    });

    it('kod boyu KISA kenardan türer — yön değişince kod büyümez', () => {
        // Uzun kenardan türetilseydi yatay A4'te kod 163 mm çıkardı ve
        // kâğıda sığmazdı.
        expect(qrPrintedSizeMm('A4', 'Portrait')).toBe(qrPrintedSizeMm('A4', 'Landscape'));
        expect(qrPrintedSizeMm('A7', 'Portrait')).toBe(41);
    });
});
