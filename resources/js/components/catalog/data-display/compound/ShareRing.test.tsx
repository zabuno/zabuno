import { describe, expect, it } from 'vitest';
import { render, screen, within } from '@testing-library/react';

import { ShareRing } from './ShareRing';

/**
 * ŞUBE HALKASI — `docs/109` §1 (Insights).
 *
 * "Bu şube markanın ne kadarı?" iki şubesi olan bir işletmenin ilk
 * sorusudur ve toplam sayı onu tam olarak gizler: 214 taramanın 200'ü tek
 * şubeden geliyorsa diğerinin karekodu duvardan düşmüş olabilir.
 *
 * Halkanın METİN KARŞILIĞI efsanedir (legend) ve bu bilerek görünürdür:
 * bir daire diliminin yüzdesini gözle kestirmek zordur — %28 ile %34 aynı
 * görünür. Efsane hem görene hem görmeyene aynı sayıyı verir.
 */
const SLICES = [
    { id: 1, label: 'Kadıköy', value: 842, percent: 58.1 },
    { id: 2, label: 'Beşiktaş', value: 511, percent: 35.3 },
    { id: 3, label: 'Bostancı', value: 96, percent: 6.6 },
];

function renderRing(slices = SLICES) {
    return render(
        <ShareRing
            slices={slices}
            description="Şube payı"
            formatValue={(value) => `${value} tarama`}
            formatPercent={(percent) => `%${percent}`}
        />,
    );
}

describe('ShareRing — elle yazılmış SVG halka', () => {
    it('halkayı bir figure olarak adlandırır', () => {
        renderRing();

        expect(screen.getByRole('figure', { name: 'Şube payı' })).toBeInTheDocument();
    });

    it('her dilimin adını, sayısını ve yüzdesini görünür metinle yazar', () => {
        renderRing();

        const legend = screen.getByRole('list');
        const rows = within(legend).getAllByRole('listitem');

        expect(rows).toHaveLength(SLICES.length);
        expect(rows[0]).toHaveTextContent('Kadıköy');
        expect(rows[0]).toHaveTextContent('842 tarama');
        expect(rows[0]).toHaveTextContent('%58.1');
    });

    it('çizimi ekran okuyucudan gizler: sayı efsanede duruyor', () => {
        const { container } = renderRing();

        const svg = container.querySelector('svg');

        expect(svg?.getAttribute('aria-hidden')).toBe('true');
        expect(svg?.getAttribute('viewBox')).toBeTruthy();
    });

    it('her dilim için bir yay çizer ve yaylar halkayı tamamlar', () => {
        const { container } = renderRing();

        const arcs = [...container.querySelectorAll('[data-role="arc"]')];

        expect(arcs).toHaveLength(SLICES.length);

        /*
            Yay uzunluğu `stroke-dasharray` ile verilir: `path` matematiği
            yerine tek bir daire ve dash deseni kullanmak, yarıçap değişince
            bozulacak trigonometriyi ortadan kaldırır.
        */
        for (const arc of arcs) {
            expect(arc.getAttribute('stroke-dasharray')).toBeTruthy();
        }
    });

    it('tek şube varken halkayı tam çizer', () => {
        // Tek şubeli bir işletmede pay %100'dür ve halka kapanır. Sıfır
        // uzunlukta bir yay, ekranda hiç halka olmaması demekti.
        const { container } = renderRing([{ id: 1, label: 'Kadıköy', value: 12, percent: 100 }]);

        expect(container.querySelectorAll('[data-role="arc"]')).toHaveLength(1);
    });

    it('dilim yokken hiç çizilmez', () => {
        const { container } = renderRing([]);

        expect(container).toBeEmptyDOMElement();
    });
});
