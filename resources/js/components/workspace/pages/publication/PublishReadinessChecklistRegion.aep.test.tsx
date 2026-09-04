import { describe, expect, it } from 'vitest';
import { render, screen, within } from '@testing-library/react';

import { PublishReadinessChecklistRegion } from './PublishReadinessChecklistRegion';
import type { DashboardMenuTree } from '../DashboardPage';

/**
 * HAZIRLIK KONTROLÜ — kanonik teslim paketi (`DESIGN_SPEC.md` §9 "Hazırlık
 * kontrolü" ve §12 "Erişilebilirlik": durum ASLA yalnız renkle anlatılmaz).
 *
 * Restoran sahibinin yolculuğu: beş maddelik liste, "yayınlayabilir miyim?"
 * sorusunun cevabıdır ve sahip ona saniyeler içinde bakar. Önceki hâlde her
 * madde "Has category: Ready" gibi düz bir cümleydi: beş satır birbirinin
 * aynıydı, biten ile eksik olan arasındaki tek fark satırın SONUNDAKİ
 * kelimeydi. Göz o kelimeyi bulmak için beş satırı da okumak zorundaydı.
 *
 * Şimdi durum ÜÇ kanalla söylenir ve hiçbiri tek başına yeterli değildir:
 *   1. BİÇİM — biten madde dolu bir onay dairesi, eksik madde boş bir daire,
 *   2. METİN BİÇİMİ — biten maddenin etiketi üstü çizili,
 *   3. METİN — "Ready" / "Needs attention" ekran okuyucuya okunur.
 *
 * Renk körü bir kullanıcı da, yüksek kontrast modundaki biri de ikisini
 * ayırt eder. Uygulanmış örnek: `dashboard/DashboardSetupJourney.tsx`.
 */
function makeMixedTree(): DashboardMenuTree {
    return {
        id: 77,
        workspaceId: 71,
        locationId: 923,
        name: 'Ana Menü',
        state: 'draft',
        categories: [
            {
                id: 5,
                menuId: 77,
                name: 'Starters',
                position: 0,
                menuItems: [
                    {
                        id: 101,
                        categoryId: 5,
                        productId: 901,
                        productName: 'Kahve',
                        priceMinorAmount: 4250,
                        currencyCode: 'TRY',
                        position: 0,
                        allergens: [],
                        isVisible: true,
                    },
                    {
                        // Adı olmayan GÖRÜNÜR ürün: "ürün adları" maddesini
                        // düşüren gerçek veri. Uydurulmuş bir bayrak değil.
                        id: 102,
                        categoryId: 5,
                        productId: 902,
                        productName: '',
                        priceMinorAmount: 1500,
                        currencyCode: 'TRY',
                        position: 1,
                        allergens: [],
                        isVisible: true,
                    },
                ],
            },
        ],
    };
}

function readyItem(): HTMLElement {
    const region = screen.getByRole('region', { name: /publish readiness checklist/i });

    return within(region)
        .getAllByRole('listitem')
        .find((item) => /Has category/i.test(item.textContent ?? '')) as HTMLElement;
}

function pendingItem(): HTMLElement {
    const region = screen.getByRole('region', { name: /publish readiness checklist/i });

    return within(region)
        .getAllByRole('listitem')
        .find((item) => /product name/i.test(item.textContent ?? '')) as HTMLElement;
}

describe('PublishReadinessChecklistRegion — durum iki kanaldan okunur', () => {
    it('biten madde ÜSTÜ ÇİZİLİ bir etiket taşır', () => {
        /*
            Üstü çizgi, "bu iş kapandı"yı RENKTEN BAĞIMSIZ söyleyen ikinci
            işarettir. Tek başına soluk bir renk, yüksek kontrast modunda
            kaybolur ve o modda liste yeniden beş eşit satıra döner.
        */
        render(<PublishReadinessChecklistRegion dashboardMenuTree={makeMixedTree()} />);

        const label = within(readyItem()).getByText(/Has category/i);

        expect(label).toHaveClass('line-through');
    });

    it('eksik madde üstü çizili DEĞİLDİR ve etiketi soluk değildir', () => {
        render(<PublishReadinessChecklistRegion dashboardMenuTree={makeMixedTree()} />);

        const label = within(pendingItem()).getByText(/product name/i);

        expect(label.className).not.toMatch(/line-through/);
        expect(label).toHaveClass('text-fg');
    });

    it('her maddede bir işaret simgesi vardır ve simge ekran okuyucudan gizlidir', () => {
        /*
            Simge DEKORATİFTİR: aynı durumu hemen yanındaki metin zaten
            söylüyor. Ekran okuyucunun ikisini de okuması, beş maddelik
            listeyi on duyuruya çevirirdi.
        */
        render(<PublishReadinessChecklistRegion dashboardMenuTree={makeMixedTree()} />);

        const doneMarker = readyItem().querySelector('svg');
        const pendingMarker = pendingItem().querySelector('svg');

        expect(doneMarker).not.toBeNull();
        expect(pendingMarker).not.toBeNull();
        expect(doneMarker?.getAttribute('aria-hidden')).toBe('true');
        expect(pendingMarker?.getAttribute('aria-hidden')).toBe('true');
    });

    it('durum METNİ de kalır: gören için işaret, duyan için kelime', () => {
        render(<PublishReadinessChecklistRegion dashboardMenuTree={makeMixedTree()} />);

        expect(readyItem().textContent ?? '').toMatch(/Ready/);
        expect(pendingItem().textContent ?? '').toMatch(/Needs attention/);

        // Durum kelimesi GÖRSEL gürültü değildir: işaret + üstü çizgi zaten
        // anlatıyor, kelime yalnız ekran okuyucu içindir.
        const statusText = within(readyItem()).getByText('Ready');
        expect(statusText).toHaveClass('sr-only');
    });
});

describe('PublishReadinessChecklistRegion — AEP tipografi ve ritim', () => {
    it('madde satırları yoğunluk jetonuyla ölçülür', () => {
        render(<PublishReadinessChecklistRegion dashboardMenuTree={makeMixedTree()} />);

        expect(readyItem()).toHaveClass('min-h-[var(--density-row-height)]');
    });

    it('etiketler gövde tabanındadır: text-meta bir etiket ölçüsü değildir', () => {
        /*
            `--text-meta` YALNIZ zaman damgası ve sayaç içindir. Bir kontrol
            maddesinin adı okunacak bir cümledir; gövde tabanına (1rem) aittir.
        */
        render(<PublishReadinessChecklistRegion dashboardMenuTree={makeMixedTree()} />);

        const label = within(pendingItem()).getByText(/product name/i);

        expect(label).toHaveClass('text-body');
        expect(label.className).not.toMatch(/text-meta/);
    });

    it('ağırlık merdiveni 400/500/700: font-semibold yoktur', () => {
        render(<PublishReadinessChecklistRegion dashboardMenuTree={makeMixedTree()} />);

        const region = screen.getByRole('region', { name: /publish readiness checklist/i });
        const classLists: string[] = [];
        region.querySelectorAll<HTMLElement>('*').forEach((el) => {
            if (typeof el.className === 'string') classLists.push(el.className);
        });
        classLists.push(region.className);

        expect(classLists.filter((list) => /font-semibold/.test(list))).toEqual([]);
        expect(classLists.filter((list) => /uppercase/.test(list))).toEqual([]);
        expect(classLists.filter((list) => /rounded-full/.test(list))).toEqual([]);
    });
});
