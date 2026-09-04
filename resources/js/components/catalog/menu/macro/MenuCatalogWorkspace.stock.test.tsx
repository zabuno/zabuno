import type React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * "BUGÜN TÜKENDİ" — `docs/82` (P1-04).
 *
 * Akşam servisinde balık bitti. Sahibin tek seçeneği ürünü GİZLEMEKTİ; o
 * zaman ürün menüden tamamen kayboluyordu ve misafir "bugün balık var mı?"
 * diye sormaya devam ediyordu.
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

function tree(outOfStock: boolean) {
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
                name: 'Balıklar',
                position: 1,
                menuItems: [
                    {
                        id: 11,
                        categoryId: 5,
                        productId: 9,
                        productName: 'Levrek',
                        priceMinorAmount: 42000,
                        currencyCode: 'TRY',
                        position: 1,
                        isVisible: true,
                        allergens: [],
                        outOfStock,
                    },
                ],
            },
        ],
    };
}

async function renderWorkspace(outOfStock = false) {
    const calls: { url: string; method: string; body: unknown }[] = [];

    vi.stubGlobal(
        'fetch',
        vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            calls.push({
                url: String(url),
                method,
                body: init?.body ? JSON.parse(String(init.body)) : null,
            });

            if (String(url) === '/sanctum/csrf-cookie') return jsonResponse(204, {});
            if (String(url).endsWith('/brand') && method === 'GET') {
                return jsonResponse(200, { id: 1, workspaceId: WORKSPACE_ID, currency: 'TRY' });
            }
            if (String(url).endsWith(`/locations/${LOCATION_ID}/menu`) && method === 'GET') {
                return jsonResponse(200, tree(outOfStock));
            }

            return jsonResponse(200, { ok: true });
        }),
    );

    const { MenuCatalogWorkspace } = (await import('./MenuCatalogWorkspace')) as unknown as {
        MenuCatalogWorkspace: React.ComponentType<{ workspaceId: number; locationId: number }>;
    };

    render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);
    await screen.findByRole('heading', { name: 'Balıklar' });

    return { calls, user: userEvent.setup() };
}

describe('kategori geneli tükendi (docs/82 kriter 3, docs/98 FF-64)', () => {
    it('bir kategorinin tamamı tek istekle tükendi işaretlenir', async () => {
        const { calls, user } = await renderWorkspace(false);

        await user.click(
            screen.getByRole('button', { name: 'Mark everything in Balıklar sold out for today' }),
        );

        await waitFor(() => {
            expect(calls.some((call) => call.url.endsWith(`/menu/${MENU_ID}/stock`))).toBe(true);
        });

        const put = calls.find((call) => call.url.endsWith(`/menu/${MENU_ID}/stock`))!;
        expect(put.method).toBe('PUT');
        // Tek istek, ürün başına değil.
        expect(put.body).toEqual({ outOfStock: [11], inStock: [] });
        expect(calls.filter((call) => call.url.endsWith('/menu-items/11/stock'))).toHaveLength(0);

        // Kategori artık geri getirmeyi öneriyor; ürün de tükenmiş görünüyor.
        expect(
            await screen.findByRole('button', {
                name: 'Mark everything in Balıklar available again',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Mark Levrek available again' }),
        ).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('tamamı tükenmiş bir kategori tek istekle geri getirilir', async () => {
        const { calls, user } = await renderWorkspace(true);

        await user.click(
            screen.getByRole('button', { name: 'Mark everything in Balıklar available again' }),
        );

        await waitFor(() => {
            const put = calls.find((call) => call.url.endsWith(`/menu/${MENU_ID}/stock`));
            expect(put?.body).toEqual({ outOfStock: [], inStock: [11] });
        });

        vi.unstubAllGlobals();
    });
});

describe('bugün tükendi (docs/82)', () => {
    it('bir ürün tükendi işaretlenir ve yayın istenmez', async () => {
        const { calls, user } = await renderWorkspace(false);

        await user.click(screen.getByRole('button', { name: 'Mark Levrek sold out for today' }));

        await waitFor(() => {
            expect(calls.some((call) => call.url.endsWith('/stock'))).toBe(true);
        });

        const put = calls.find((call) => call.url.endsWith('/stock'))!;
        expect(put.method).toBe('PUT');
        expect(put.url).toBe(`/api/workspaces/${WORKSPACE_ID}/menu-items/11/stock`);
        expect(put.body).toEqual({ outOfStock: true });

        // Yayın İSTENMEZ: "balık bitti" servis sırasında geçerli, dakikalık
        // bir gerçektir ve yayın taslaktaki yarım işi de canlıya iterdi.
        expect(calls.some((call) => call.url.endsWith('/publications'))).toBe(false);

        // Düğme artık geri getirmeyi öneriyor.
        expect(
            await screen.findByRole('button', { name: 'Mark Levrek available again' }),
        ).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('tükenmiş bir ürün geri getirilir', async () => {
        const { calls, user } = await renderWorkspace(true);

        await user.click(screen.getByRole('button', { name: 'Mark Levrek available again' }));

        await waitFor(() => {
            expect(calls.some((call) => call.url.endsWith('/stock'))).toBe(true);
        });

        expect(calls.find((call) => call.url.endsWith('/stock'))!.body).toEqual({
            outOfStock: false,
        });

        vi.unstubAllGlobals();
    });

    it('tükendi ile gizlemek iki ayrı denetimdir', async () => {
        await renderWorkspace(false);

        /*
            İki denetim AYRI kalır: gizli bir ürün menüde YOKTUR, tükenmiş bir
            ürün menüde VARDIR ama bugün verilemez.

            GÜNCELLENDİ (kanonik teslim paketi, `DESIGN_SPEC` §3): ikisi de
            satırda durur ama BİÇİMLERİ farklıdır ve ayrımı artık biçim
            taşır. "Tükendi" anlık bir eylemdir — basılıp bırakılan bir ikon
            düğmesi (`aria-pressed`). Görünürlük kalıcı bir hâldir — açık
            kalan bir anahtar (`role="switch"`, `aria-checked`).

            FF-102'nin çözdüğü sorun (etiketsiz bir kutu ile "tükendi"
            düğmesinin karışması) burada da çözülmüş durumda: anahtar tam
            cümleyi taşıyor. Kutuyu taşma menüsüne saklamak ise durumu da
            saklıyordu.
        */
        const stockButton = screen.getByRole('button', {
            name: 'Mark Levrek sold out for today',
        });
        expect(stockButton).toHaveAttribute('aria-pressed', 'false');

        const visibilitySwitch = screen.getByRole('switch', {
            name: 'Show Levrek on the menu',
        });
        expect(visibilitySwitch).toHaveAttribute('aria-checked', 'true');
        expect(visibilitySwitch).not.toBe(stockButton);

        vi.unstubAllGlobals();
    });
});
