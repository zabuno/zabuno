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
        /*
            ÖLÇÜLDÜ (2026-09-06, FF-202): hedef `settings/brand` idi ve marka
            yokken o sekme "Loading your brand…" yazan, sıfır alan içeren bir
            ekrandı — ilk dokunuş bir çıkmaz sokaktı. Oluşturma formu `brand`
            bölümünde çizilir.
        */
        expect(go).toHaveBeenCalledWith('brand');
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

    it('her şey bitince "menün yayında, masalara kodu bas" der, karekod indirmeye götürür ve ilk yayın süresini yazar', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async (url: string) => {
                expect(String(url)).toBe('/api/workspaces/7/setup-progress');
                return {
                    ok: true,
                    status: 200,
                    json: async () => ({
                        steps: {
                            brand: { done: true },
                            location: { done: true },
                            menu: { done: true, itemCount: 1 },
                            publication: { done: true, id: 55, version: 1 },
                            qr: { done: true, activeCount: 1 },
                        },
                        doneCount: 5,
                        total: 5,
                        firstPublishedAfterMinutes: 27,
                    }),
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
        // Bitti tanımı GÖRÜNÜR: ne oldu + tek somut sonraki iş (`docs/101` Faz 3).
        expect(await within(now).findByText(/Your menu is live/)).toBeInTheDocument();
        expect(within(now).getByRole('button')).toHaveTextContent('Download QR codes');

        // Süre YALNIZ gerçekleştiyse ve sunucunun sayısıyla (`docs/110` §7).
        expect(
            screen.getByText('First published 27 minutes after opening this workspace.'),
        ).toBeInTheDocument();

        // Kurulum bitince yardım bağlantısı sıradaki günlük işe gider.
        expect(screen.getByRole('link')).toHaveAttribute('href', '/help#help-price');
    });

    it('yayın yokken süre cümlesi çizilmez ve takılma çıkışı makalenin kendisidir', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(
                async () =>
                    ({
                        ok: true,
                        status: 200,
                        json: async () => ({
                            steps: {
                                brand: { done: true },
                                location: { done: true },
                                menu: { done: true, itemCount: 1 },
                                publication: { done: false },
                                qr: { done: false, activeCount: 0 },
                            },
                            doneCount: 3,
                            total: 5,
                        }),
                    }) as Response,
            ),
        );

        render(
            <DashboardSetupJourney
                brand={BRAND}
                location={LOCATION}
                dashboardMenuTree={TREE}
                workspaceId={7}
                onNavigateToSection={() => {}}
            />,
        );

        expect(await screen.findAllByText('Not connected yet.')).toHaveLength(2);
        expect(screen.queryByText(/First published/)).toBeNull();

        // Ölçüm: ÖNCE bu kartta sıfır bağlantı vardı. Şimdi bir tane, gerçek makaleye.
        const link = screen.getByRole('link', { name: /Your first 15 minutes/ });
        expect(link).toHaveAttribute('href', '/help');
        expect(link).toHaveAttribute('target', '_blank');
    });
});
