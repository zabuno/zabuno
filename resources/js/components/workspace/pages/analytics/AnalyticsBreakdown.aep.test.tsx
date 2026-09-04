import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';

import { AnalyticsBreakdown } from './AnalyticsBreakdown';

/**
 * KIRILIM TABLOSUNUN AEP GRAMERİ — kanonik teslim paketi
 * (`DESIGN_SPEC.md` "Kart grameri" + §5 "Masaya göre (ilk 5)").
 *
 * Sahibin yolculuğu: iki şubesi var, toplam 15 tarama görüyor ve "hangi şube?"
 * diye soruyor. Cevabı veren şey bu tablodur. Ama bugün tablo ÇIPLAK duruyor:
 * ne bir kart sınırı var, ne başlık satırının gövdeden ayrıldığı bir ton.
 * Sayfada üst üste iki kırılım olduğunda (şube ve QR kodu) nerede birinin
 * bitip diğerinin başladığı belli olmuyor; sahip "Beşiktaş" satırını yanlış
 * başlığın altında okuyabiliyor.
 *
 * Teslim paketinin cevabı: TEK kart, içinde ince ayraçlı satırlar. Satırın
 * kendisi kart DEĞİLDİR — satırları ayrı kutulara koymak, aynı listenin
 * parçalarını birbirinden koparır ve karşılaştırmayı imkânsızlaştırır.
 * Tablo başlığı `surface-subtle` tonuyla ayrışır (`DESIGN_SPEC.md` §2).
 */
const ROWS = [
    { id: 1, label: 'Kadıköy', qrResolveCount: 12, menuOpenCount: 9 },
    { id: 2, label: 'Beşiktaş', qrResolveCount: 3, menuOpenCount: 3 },
];

function classNamesOf(root: HTMLElement): string[] {
    const all: string[] = [];
    if (root.className) all.push(String(root.className));
    root.querySelectorAll<HTMLElement>('*').forEach((element) => {
        if (typeof element.className === 'string' && element.className) {
            all.push(element.className);
        }
    });

    return all;
}

describe('AnalyticsBreakdown — AEP kart grameri', () => {
    it('tabloyu tek bir kartın içine alır', () => {
        render(<AnalyticsBreakdown heading="By location" rows={ROWS} />);

        const card = screen.getByRole('table').closest('section');

        expect(card).toHaveClass('rounded-[var(--radius-lg)]');
        expect(card).toHaveClass('border');
        expect(card).toHaveClass('bg-[var(--color-surface)]');
        /*
            `overflow-hidden`: başlık satırının soluk dolgusu kartın
            yuvarlatılmış üst köşelerini kesip köşeleri kareleştirir. Bu,
            ekranda "kart yarım kalmış" gibi görünen tek pikselllik bir
            kusurdur ve yalnız burada düzeltilebilir.
        */
        expect(card).toHaveClass('overflow-hidden');
    });

    it('kart başlığı kırılımın adını taşır ve gövdeden ayraçla ayrılır', () => {
        render(<AnalyticsBreakdown heading="By location" rows={ROWS} />);

        const heading = screen.getByRole('heading', { name: 'By location' });

        expect(heading).toHaveClass('font-bold');
        // 600 yok: AEP yalnız 400/500/700 yayınlıyor.
        expect(heading.className).not.toMatch(/font-semibold/);
    });

    it('tablo başlık satırı soluk tonla ayrışır, büyük harfe çevrilmez', () => {
        render(<AnalyticsBreakdown heading="By location" rows={ROWS} />);

        const nameHeader = screen.getByRole('columnheader', { name: 'Name' });

        expect(nameHeader.closest('tr')).toHaveClass('bg-surface-subtle');
        expect(nameHeader).toHaveClass('font-bold');
        expect(nameHeader.className).not.toMatch(/uppercase/);
    });

    /**
     * AYRAÇ ÜSTE KONUR.
     *
     * Alt ayraçlı bir listede son satırın ayracını ayrıca susturmak gerekir;
     * unutulduğunda kartın kendi kenarlığıyla çakışan ikinci bir çizgi
     * belirir. Üstten ayraç, eklenen her yeni satırı kendiliğinden doğru
     * çizer. Uygulanmış örnek: `team/TeamMemberList.tsx`.
     */
    it('satırlar üstten ince ayraçlıdır ve kendileri kart değildir', () => {
        render(<AnalyticsBreakdown heading="By location" rows={ROWS} />);

        const row = screen.getByText('Kadıköy').closest('tr');

        expect(row).toHaveClass('border-t');
        expect(row).not.toHaveClass('border-b');
        expect(row?.className ?? '').not.toMatch(/rounded/);
    });

    it('satır yüksekliği yoğunluk jetonundan gelir, elle yazılmış bir dolgudan değil', () => {
        render(<AnalyticsBreakdown heading="By location" rows={ROWS} />);

        const cell = screen.getByText('Kadıköy').closest('td');

        expect(cell).toHaveClass('h-[var(--density-row-height)]');
        // Kart başlığının yatay dolgusuyla AYNI hiza: `OpsCard` başlığı
        // `--space-5` kullanıyor; satırlar kaymamalı.
        expect(cell).toHaveClass('px-[var(--space-5)]');
    });

    it('sayı hücreleri eşit genişlikli rakamlarla ve sona yaslı çizilir', () => {
        render(<AnalyticsBreakdown heading="By location" rows={ROWS} />);

        const numeric = screen.getByText('12').closest('td');

        expect(numeric).toHaveClass('tabular-nums');
        expect(numeric).toHaveClass('text-end');
    });

    /**
     * Ölçek dışı hiçbir sınıf kalmasın: 600 ağırlık, büyük harfe çevirme ve
     * `rounded-full` AEP'te yok. Üçü de tek bir taramayla dondurulur ki
     * ileride eklenen bir satır sessizce ölçeğin dışına çıkmasın.
     */
    it('AEP ölçeğinin dışında sınıf taşımaz', () => {
        const { container } = render(<AnalyticsBreakdown heading="By location" rows={ROWS} />);

        for (const className of classNamesOf(container)) {
            expect(className).not.toMatch(/font-semibold/);
            expect(className).not.toMatch(/\buppercase\b/);
            expect(className).not.toMatch(/rounded-full/);
        }
    });
});
