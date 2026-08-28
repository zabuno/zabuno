import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { AnalyticsBreakdown } from './AnalyticsBreakdown';

/**
 * Kırılım — `docs/68`.
 */
const ROWS = [
    { id: 1, label: 'Kadıköy', qrResolveCount: 12, menuOpenCount: 9 },
    { id: 2, label: 'Beşiktaş', qrResolveCount: 3, menuOpenCount: 3 },
];

describe('analitik kırılımı', () => {
    /**
     * Toplam sayı, iki şubesi olan bir işletmede birinin az taranmasını
     * gizler. Kırılımın işi o gizlenen şeyi görünür kılmaktır.
     */
    it('her satırın kendi sayılarını gösterir', () => {
        render(<AnalyticsBreakdown heading="By location" rows={ROWS} />);

        const kadikoy = screen.getByText('Kadıköy').closest('tr');
        expect(kadikoy).toHaveTextContent('12');
        expect(kadikoy).toHaveTextContent('9');

        const besiktas = screen.getByText('Beşiktaş').closest('tr');
        expect(besiktas).toHaveTextContent('3');
    });

    /**
     * Tek satırlık bir kırılım, hemen üstündeki toplamın kelimesi kelimesine
     * tekrarıdır. Kırılımın değeri KARŞILAŞTIRMADIR; karşılaştıracak ikinci
     * bir şey yoksa değeri de yoktur.
     */
    it('karşılaştıracak ikinci satır yoksa hiç çizilmez', () => {
        const { container } = render(<AnalyticsBreakdown heading="By location" rows={[ROWS[0]]} />);

        expect(container).toBeEmptyDOMElement();
    });

    it('hiç satır yoksa çizilmez', () => {
        const { container } = render(<AnalyticsBreakdown heading="By location" rows={[]} />);

        expect(container).toBeEmptyDOMElement();
    });

    /**
     * Uzun bir karekod jetonu tabloyu taşırmamalı, ama tamamı erişilebilir
     * kalmalı: kullanıcı basılı koddaki adresle eşleştirebilmeli.
     */
    it('uzun etiketi kırpar ama tamamını başlıkta tutar', () => {
        const token = 'a'.repeat(43);
        render(
            <AnalyticsBreakdown
                heading="By QR code"
                rows={[
                    { id: 1, label: token, qrResolveCount: 4, menuOpenCount: 4 },
                    { id: 2, label: 'b'.repeat(43), qrResolveCount: 1, menuOpenCount: 1 },
                ]}
            />,
        );

        expect(screen.getByText(token)).toHaveAttribute('title', token);
    });
});
