import { describe, expect, it } from 'vitest';
import { render, screen, within } from '@testing-library/react';

import { RankBarList } from './RankBarList';

/**
 * SIRALAMA ÇUBUKLARI — `docs/109` §1 (Insights, "masaya göre ilk 5").
 *
 * Sahibin sorusu "hangi masa çekiyor?" değil, "hangi masanın karekodu
 * çalışmıyor?"dur. Çıplak bir sayı listesinde 31 ile 12 arasındaki fark
 * okunur ama HİSSEDİLMEZ; yan yana duran iki çubukta ilk bakışta görünür.
 *
 * Liste KIRPILIR. On iki masalık bir listenin tamamı, sahibin bakması
 * gereken beş satırı görünmez kılar — sıralamanın değeri ilk birkaç
 * satırdadır.
 */
const ROWS = [
    { id: 3, label: 'Masa 3', value: 31 },
    { id: 8, label: 'Masa 8', value: 22 },
    { id: 11, label: 'Masa 11', value: 17 },
    { id: 6, label: 'Masa 6', value: 14 },
    { id: 1, label: 'Masa 1', value: 12 },
    { id: 9, label: 'Masa 9', value: 3 },
];

function renderList(rows = ROWS, limit?: number) {
    return render(<RankBarList rows={rows} limit={limit} valueLabel="tarama" />);
}

describe('RankBarList', () => {
    it('listeyi verilen sayıda satıra kırpar', () => {
        renderList(ROWS, 5);

        expect(screen.getAllByRole('listitem')).toHaveLength(5);
        expect(screen.queryByText('Masa 9')).not.toBeInTheDocument();
    });

    it('çubuğu en yüksek satıra göre ölçekler', () => {
        renderList(ROWS, 5);

        const items = screen.getAllByRole('listitem');
        const first = items[0].querySelector('[data-role="rank-bar"]') as HTMLElement;
        const second = items[1].querySelector('[data-role="rank-bar"]') as HTMLElement;

        expect(first.style.inlineSize).toBe('100%');
        // 22/31 ≈ %71 — oran ölçekten okunur, elle yazılmış bir sayıdan değil.
        expect(Number.parseFloat(second.style.inlineSize)).toBeCloseTo(71, 0);
    });

    it('sayıyı eşit genişlikli rakamlarla yazar', () => {
        renderList(ROWS, 5);

        /*
            `tabular-nums` şart: oransal rakamlarda "1" diğerlerinden dardır,
            alt alta duran sayılar farklı genişlikte görünür ve sütun kayar.
            Karşılaştırma için var olan bir listede bu, listenin tek işini
            bozar.
        */
        const value = within(screen.getAllByRole('listitem')[0]).getByText('31');

        expect(value.className).toMatch(/tabular-nums/);
    });

    it('en yüksek değer sıfırken bölme hatasına düşmez', () => {
        // Ölçülmüş ama hiç taranmamış masalar gerçektir: karekodlar basıldı,
        // henüz kimse okutmadı. 0/0 çubuğu `NaN` genişlikle çizerdi.
        renderList([{ id: 1, label: 'Masa 1', value: 0 }]);

        const bar = screen.getByRole('listitem').querySelector('[data-role="rank-bar"]');

        expect((bar as HTMLElement).style.inlineSize).toBe('0%');
    });

    it('satır yokken hiç çizilmez', () => {
        const { container } = renderList([]);

        expect(container).toBeEmptyDOMElement();
    });

    it('sabit değerli hap sınıfı kullanmaz', () => {
        const { container } = renderList(ROWS, 5);

        // DS-RADIUS-ROOT-02: `rounded-full` derlenmiş CSS'te sabit bir sayıya
        // çözülür ve token kökünden geçmez.
        expect(container.innerHTML).not.toMatch(/\brounded-full\b/);
    });
});
