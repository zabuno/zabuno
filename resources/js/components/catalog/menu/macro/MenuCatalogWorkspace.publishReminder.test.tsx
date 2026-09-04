import type React from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * `docs/101` Y3 (ACEMI-Y3-PUBLISH-REMINDER-01): fiyat kaydedilince masada
 * hiçbir şey değişmez — yayınlanana kadar misafir son yayınlanan menüyü
 * görür. Unutulan adım budur; ekran onu söyler ve yayına götürür.
 */
const WORKSPACE_ID = 7;
const LOCATION_ID = 3;
const MENU_ID = 42;

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        headers: new Headers(),
        json: async () => body,
    } as Response;
}

describe('fiyat sonrası yayın hatırlatması', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('fiyat kaydedilince hatırlatma çıkar ve "Publish now" yayın ekranına götürür', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async (url: string, init?: RequestInit) => {
                const method = (init?.method ?? 'GET').toUpperCase();
                const u = String(url);
                if (u === '/sanctum/csrf-cookie') return jsonResponse(204, {});
                if (u.endsWith('/brand') && method === 'GET') {
                    return jsonResponse(200, { id: 1, workspaceId: WORKSPACE_ID, currency: 'TRY' });
                }
                if (u.endsWith(`/locations/${LOCATION_ID}/menu`) && method === 'GET') {
                    return jsonResponse(200, {
                        id: MENU_ID,
                        workspaceId: WORKSPACE_ID,
                        locationId: LOCATION_ID,
                        name: 'Ana Menü',
                        state: 'draft',
                        categories: [
                            {
                                id: 5,
                                menuId: MENU_ID,
                                name: 'Kebaplar',
                                position: 1,
                                menuItems: [
                                    {
                                        id: 9,
                                        categoryId: 5,
                                        productId: 9,
                                        productName: 'Adana',
                                        priceMinorAmount: 25000,
                                        currencyCode: 'TRY',
                                        position: 1,
                                        allergens: [],
                                        isVisible: true,
                                    },
                                ],
                            },
                        ],
                    });
                }
                if (u.includes('/price') && method === 'PUT') {
                    return jsonResponse(200, { priceMinorAmount: 27000, currencyCode: 'TRY' });
                }
                return jsonResponse(200, { ok: true });
            }),
        );

        const goToSection = vi.fn();
        const { MenuCatalogWorkspace } = (await import('./MenuCatalogWorkspace')) as unknown as {
            MenuCatalogWorkspace: React.ComponentType<{
                workspaceId: number;
                locationId: number;
                onNavigateToSection?: (section: string) => void;
            }>;
        };
        render(
            <MenuCatalogWorkspace
                workspaceId={WORKSPACE_ID}
                locationId={LOCATION_ID}
                onNavigateToSection={goToSection}
            />,
        );
        await screen.findByRole('heading', { name: 'Kebaplar' });
        const user = userEvent.setup();

        expect(screen.queryByText(/Guests still see the last published menu/)).toBeNull();

        await user.click(screen.getByRole('button', { name: 'Edit price for Adana' }));
        const priceField = await screen.findByLabelText('Price — Adana');
        await user.clear(priceField);
        await user.type(priceField, '270');
        await user.click(screen.getByRole('button', { name: 'Save price' }));

        await waitFor(() => {
            expect(
                screen.getByText(/Guests still see the last published menu/),
            ).toBeInTheDocument();
        });

        await user.click(screen.getByRole('button', { name: 'Publish now' }));
        expect(goToSection).toHaveBeenCalledWith('publication');
    });
});
