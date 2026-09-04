import { describe, expect, it } from 'vitest';
import { render, screen, within } from '@testing-library/react';

import { PublishedSnapshotRegion } from './PublishedSnapshotRegion';
import { DraftMenuPreviewRegion } from './DraftMenuPreviewRegion';
import type { CurrentPublication } from './PublicationStatusRegion';
import type { DashboardMenuTree } from '../DashboardPage';

/**
 * TİPOGRAFİ ÖLÇEĞİ — kanonik teslim paketi (`TOKEN_MAP.md` / `DESIGN_SPEC.md`
 * §12): gövde tabanı 1rem, `--text-meta` YALNIZ zaman damgası ve sayaç için.
 *
 * Restoran sahibinin yolculuğu: "yayında ne var?" ve "taslakta ne var?"
 * ekranları, sahibin menüsünü OKUDUĞU yerlerdir. Kategori adı bir etikettir,
 * alerjen listesi bir cümledir, "Gizli" bir durumdur — üçü de okunacak
 * metindir ve gövde tabanına aittir. `text-meta`ya düşürüldüklerinde ekran
 * "ikincil bilgi" diye bir hiyerarşi uyduruyordu; oysa sahip menüsünü
 * ikincil bilgi olarak okumaz.
 *
 * Zaman damgası ise gerçekten ikincildir ve `tabular-nums` taşır: alt alta
 * gelen yayın tarihlerinde haneler kaymaz.
 */
const CURRENT: CurrentPublication = {
    id: 12,
    workspaceId: 7,
    menuId: 42,
    locationId: 3,
    version: 4,
    state: 'published',
    publishedAt: '2026-08-28T18:00:00Z',
    snapshot: {
        categories: [{ name: 'Starters', menuItems: [{ productName: 'Kahve' }] }],
    },
};

function makeTree(): DashboardMenuTree {
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
                        allergens: ['milk'],
                        isVisible: true,
                    },
                ],
            },
        ],
    };
}

describe('PublishedSnapshotRegion — meta yalnız zaman damgasıdır', () => {
    it('kategori adı gövde tabanındadır', () => {
        render(<PublishedSnapshotRegion current={CURRENT} />);

        const category = screen.getByText('Starters');

        expect(category).toHaveClass('text-body');
        expect(category.className).not.toMatch(/text-meta/);
    });

    it('yayın zaman damgası meta ve tabular-nums taşır', () => {
        render(<PublishedSnapshotRegion current={CURRENT} />);

        const stamp = screen.getByText(/2026-08-28T18:00:00Z/);

        expect(stamp).toHaveClass('text-meta');
        expect(stamp).toHaveClass('tabular-nums');
    });
});

describe('DraftMenuPreviewRegion — meta yalnız zaman damgasıdır', () => {
    it('kategori adı, alerjen satırı ve görünürlük durumu gövde tabanındadır', () => {
        render(<DraftMenuPreviewRegion dashboardMenuTree={makeTree()} />);

        const region = screen.getByRole('region', { name: /draft menu preview/i });

        expect(within(region).getByText('Starters')).toHaveClass('text-body');
        expect(within(region).getByText(/Allergens/)).toHaveClass('text-body');
        expect(within(region).getByText('Visible')).toHaveClass('text-body');
    });

    it('fiyat hizalı yazılır', () => {
        /*
            Fiyatlar ALT ALTA okunur ve karşılaştırılır. Orantılı rakamda
            "42,50" ile "1.500,00" farklı ritimde çizilir; göz sütunu
            kaybeder.
        */
        render(<DraftMenuPreviewRegion dashboardMenuTree={makeTree()} />);

        const region = screen.getByRole('region', { name: /draft menu preview/i });

        expect(within(region).getByText(/42[.,]50/)).toHaveClass('tabular-nums');
    });

    it('iki bölgede de 600 ağırlık, büyük harf ve rounded-full yoktur', () => {
        render(
            <>
                <PublishedSnapshotRegion current={CURRENT} />
                <DraftMenuPreviewRegion dashboardMenuTree={makeTree()} />
            </>,
        );

        const classLists: string[] = [];
        document.querySelectorAll<HTMLElement>('*').forEach((el) => {
            if (typeof el.className === 'string') classLists.push(el.className);
        });

        expect(classLists.filter((list) => /font-semibold/.test(list))).toEqual([]);
        expect(classLists.filter((list) => /uppercase/.test(list))).toEqual([]);
        expect(classLists.filter((list) => /rounded-full/.test(list))).toEqual([]);
    });
});
