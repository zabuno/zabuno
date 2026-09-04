import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { DashboardSetupJourney } from './DashboardSetupJourney';
import type { DashboardMenuTree } from '../DashboardPage';

/**
 * `docs/101` A1/A6 (FF-73): Home'da TEK "şimdi" düğmesi; bitmemiş ilk adımı
 * bir FİİLLE söyler ve oraya götürür; hepsi bitince "her şey hazır".
 */
const TREE: DashboardMenuTree = {
    id: 42,
    workspaceId: 7,
    locationId: 3,
    name: 'Ana Menü',
    state: 'draft',
    categories: [{ id: 5, menuId: 42, name: 'Kebaplar', position: 1, menuItems: [] }],
};

const BRAND = { id: 1, name: 'Zeytin' } as never;
const LOCATION = { id: 3, display_name: 'Kadıköy' } as never;

describe('Home "şimdi" düğmesi (ACEMI-A1-NOW-01)', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('hiçbir şey yokken tek düğme "restoranının adını yaz" der ve marka ekranına götürür', async () => {
        const user = userEvent.setup();
        const go = vi.fn();
        render(
            <DashboardSetupJourney
                brand={null}
                location={null}
                dashboardMenuTree={null}
                onNavigateToSection={go}
            />,
        );

        const now = screen.getByRole('region', { name: 'What to do now' });
        const buttons = within(now).getAllByRole('button');
        expect(buttons).toHaveLength(1);
        expect(buttons[0]).toHaveTextContent('Name your restaurant');

        await user.click(buttons[0]);
        expect(go).toHaveBeenCalledWith('settings/brand');
    });

    it('marka ve şube varken, menü boşken "ilk ürününü ekle" der', () => {
        render(
            <DashboardSetupJourney
                brand={BRAND}
                location={LOCATION}
                dashboardMenuTree={TREE}
                onNavigateToSection={() => {}}
            />,
        );

        const now = screen.getByRole('region', { name: 'What to do now' });
        expect(within(now).getByRole('button')).toHaveTextContent('Add your first product');
    });

    it('her şey bitince düğme yerine "her şey hazır" ve karekod ekranına bağlantı', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async (url: string) => {
                if (String(url).includes('/publications/current')) {
                    return { ok: true, status: 200, json: async () => ({ id: 55 }) } as Response;
                }
                return {
                    ok: true,
                    status: 200,
                    json: async () => [{ state: 'active' }],
                } as Response;
            }),
        );
        const fullTree: DashboardMenuTree = {
            ...TREE,
            categories: [
                {
                    ...TREE.categories[0],
                    menuItems: [
                        {
                            id: 9,
                            categoryId: 5,
                            productId: 9,
                            priceMinorAmount: 25000,
                            currencyCode: 'TRY',
                            position: 1,
                            allergens: [],
                            isVisible: true,
                        },
                    ],
                },
            ],
        };

        render(
            <DashboardSetupJourney
                brand={BRAND}
                location={LOCATION}
                dashboardMenuTree={fullTree}
                workspaceId={7}
                onNavigateToSection={() => {}}
            />,
        );

        const now = screen.getByRole('region', { name: 'What to do now' });
        expect(await within(now).findByText(/Everything is set up/)).toBeInTheDocument();
        expect(within(now).getByRole('button')).toHaveTextContent('Open QR codes');
    });
});
