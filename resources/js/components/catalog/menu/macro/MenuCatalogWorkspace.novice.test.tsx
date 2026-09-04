import type React from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

/**
 * `docs/101` A5/A8 (FF-73): fotoğraftan/CSV içe aktarma TEK kutuda; menü
 * boşken açık (ilk adım oradadır), ürün varken kapalı (uzman aracı).
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

function tree(withItem: boolean) {
    return {
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
                menuItems: withItem
                    ? [
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
                      ]
                    : [],
            },
        ],
    };
}

async function renderWorkspace(withItem: boolean) {
    vi.stubGlobal(
        'fetch',
        vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            if (String(url) === '/sanctum/csrf-cookie') return jsonResponse(204, {});
            if (String(url).endsWith('/brand') && method === 'GET') {
                return jsonResponse(200, { id: 1, workspaceId: WORKSPACE_ID, currency: 'TRY' });
            }
            if (String(url).endsWith(`/locations/${LOCATION_ID}/menu`) && method === 'GET') {
                return jsonResponse(200, tree(withItem));
            }
            return jsonResponse(200, { ok: true });
        }),
    );

    const { MenuCatalogWorkspace } = (await import('./MenuCatalogWorkspace')) as unknown as {
        MenuCatalogWorkspace: React.ComponentType<{ workspaceId: number; locationId: number }>;
    };

    render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);
    await screen.findByRole('heading', { name: 'Kebaplar' });
}

describe('içe aktarma kutusu (ACEMI-A5-TOOLS-01)', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('menü boşken kutu açık ve yol tarifi okunur', async () => {
        await renderWorkspace(false);

        const tools = screen.getByRole('group', { name: 'Bring in a whole menu' });
        expect(tools.closest('details')).toHaveAttribute('open');
        expect(screen.getByText(/Start here/)).toBeInTheDocument();
    });

    it('ürün varken kutu kapalı, yol tarifi yok', async () => {
        await renderWorkspace(true);

        const tools = screen.getByRole('group', { name: 'Bring in a whole menu' });
        expect(tools.closest('details')).not.toHaveAttribute('open');
        expect(screen.queryByText(/Start here/)).toBeNull();
    });
});
