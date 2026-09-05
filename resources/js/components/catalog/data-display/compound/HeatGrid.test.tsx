import { describe, expect, it } from 'vitest';
import { render, screen, within } from '@testing-library/react';

import { HeatGrid } from './HeatGrid';

/**
 * SAAT × GÜN ISI HARİTASI — `docs/109` §1 (Insights).
 *
 * Sahibin bu haritadan çıkardığı karar somut: personeli hangi saate koyacağı,
 * mutfağın hangi saate hazırlanacağı. "Günde 30 tarama" bu kararı vermez;
 * "cumartesi 13:00'te 30 tarama" verir.
 *
 * `null` hücre "sıfır" DEĞİLDİR. Sunucu, tek bir ziyaretçiye dayanan
 * hücreleri yayımlamıyor: "salı 03:00 · 1 tarama" bir istatistik değil, bir
 * kişinin o gece oraya girdiğinin kaydıdır. Bu bileşen o ayrımı KORUMAK
 * zorunda — gizlenmiş hücreyi sıfır gibi çizmek, sahibe olmayan bir
 * boşluğu gösterirdi.
 */
function values(overrides: Record<number, number | null> = {}): (number | null)[] {
    // `?? 0` KULLANILMAZ: burada `null` bir "değer yok" değil, GİZLENMİŞ
    // hücrenin kendisidir ve sıfıra çevrilirse test tam da ayırt etmesi
    // gereken şeyi ayırt edemez.
    return Array.from({ length: 24 }, (_, hour) => (hour in overrides ? overrides[hour] : 0));
}

const ROWS = [
    { label: 'Pazartesi', values: values({ 12: 4 }) },
    { label: 'Salı', values: values({ 13: 30, 3: null }) },
];

function renderGrid(rows = ROWS) {
    return render(
        <HeatGrid
            rows={rows}
            description="Saatlere göre yoğunluk"
            columnLabel="Gün"
            hourLabel={(hour) => `${String(hour).padStart(2, '0')}:00`}
            withheldLabel="gizlendi"
        />,
    );
}

describe('HeatGrid — elle yazılmış SVG ısı haritası', () => {
    it('haritayı bir figure olarak adlandırır', () => {
        renderGrid();

        expect(screen.getByRole('figure', { name: 'Saatlere göre yoğunluk' })).toBeInTheDocument();
    });

    it('aynı sayıları taşıyan bir tablo da çizer', () => {
        renderGrid();

        const table = screen.getByRole('table');

        expect(within(table).getByRole('columnheader', { name: '13:00' })).toBeInTheDocument();
        expect(within(table).getByRole('rowheader', { name: 'Salı' })).toBeInTheDocument();

        const tuesday = within(table).getByRole('row', { name: /Salı/ });

        expect(within(tuesday).getAllByRole('cell')[13]).toHaveTextContent('30');
    });

    it('gizlenmiş hücreyi sıfır olarak DEĞİL, gizlenmiş olarak yazar', () => {
        renderGrid();

        const tuesday = within(screen.getByRole('table')).getByRole('row', { name: /Salı/ });

        /*
            Bu ayrım gizliliğin görünen yüzüdür. Gizlenen hücreyi "0" yazmak,
            eşik kuralını korurken sahibe YANLIŞ bir olgu söylerdi: o saatte
            kimse gelmedi. Oysa geldi — yalnız tek kişiydi ve sayısı
            yayımlanamaz.
        */
        expect(within(tuesday).getAllByRole('cell')[3]).toHaveTextContent('gizlendi');
        expect(within(tuesday).getAllByRole('cell')[3]).not.toHaveTextContent('0');
    });

    it('çizimi ekran okuyucudan gizler', () => {
        const { container } = renderGrid();

        const svg = container.querySelector('svg');

        expect(svg?.getAttribute('aria-hidden')).toBe('true');
        expect(svg?.getAttribute('viewBox')).toBeTruthy();
    });

    it('yoğunluğu en yüksek hücreye göre ölçekler', () => {
        const { container } = renderGrid();

        const cells = [...container.querySelectorAll('[data-role="heat-cell"]')];

        const busiest = cells.find((cell) => cell.getAttribute('data-value') === '30');
        const quiet = cells.find((cell) => cell.getAttribute('data-value') === '4');
        const empty = cells.find((cell) => cell.getAttribute('data-value') === '0');

        expect(Number(busiest?.getAttribute('fill-opacity'))).toBe(1);
        expect(Number(quiet?.getAttribute('fill-opacity'))).toBeLessThan(1);
        expect(Number(quiet?.getAttribute('fill-opacity'))).toBeGreaterThan(0);
        expect(Number(empty?.getAttribute('fill-opacity'))).toBe(0);
    });

    it('hiç yoğunluk yokken bölme hatasına düşmez', () => {
        // Her hücresi sıfır olan bir hafta gerçektir: menü yayında ama kimse
        // taramamış. En yüksek değer sıfırken ölçek 0/0 olur ve bileşen
        // `NaN` opaklıkla çizilirdi — yani hiç çizilmezdi.
        renderGrid([{ label: 'Pazartesi', values: values() }]);

        expect(screen.getByRole('figure')).toBeInTheDocument();
    });

    it('satır yokken hiç çizilmez', () => {
        const { container } = renderGrid([]);

        expect(container).toBeEmptyDOMElement();
    });
});
