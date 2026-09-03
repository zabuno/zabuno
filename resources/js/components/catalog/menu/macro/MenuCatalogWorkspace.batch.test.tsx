import type React from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * `docs/98` FF-75 — 10'dan çok sayfa kuyruğa gider; ekran partiyi izler ve
 * toplayıcının TEK listesini gösterir.
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

describe('toplu okuma (ORKESTRA-UI-01)', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it(
        '11 fotoğraf seçilince parti başlar, ilerleme okunur, toplayıcı listesi gelir',
        { timeout: 20000 },
        async () => {
            let polls = 0;
            const media = Array.from({ length: 11 }, (_, i) => ({
                id: 100 + i,
                altText: `Sayfa ${i + 1}`,
            }));
            const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
                const method = (init?.method ?? 'GET').toUpperCase();
                const u = String(url);
                if (u === '/sanctum/csrf-cookie') return jsonResponse(204, {});
                if (u.endsWith('/brand') && method === 'GET')
                    return jsonResponse(200, { id: 1, workspaceId: WORKSPACE_ID, currency: 'TRY' });
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
                                menuItems: [],
                            },
                        ],
                    });
                }
                if (u.endsWith('/ai/availability'))
                    return jsonResponse(200, {
                        available: true,
                        capabilities: { 'menu.extract': 'available' },
                    });
                if (u.includes('/media') && method === 'GET' && !u.includes('ai'))
                    return jsonResponse(200, {
                        data: media.map((m) => ({
                            ...m,
                            slot: 'menuImportSource',
                            status: 'ready',
                        })),
                    });
                if (u.endsWith('/ai-batches') && method === 'POST')
                    return jsonResponse(202, { id: 9, totalPages: 11, rejected: [] });
                if (u.endsWith('/ai-batches/9') && method === 'GET') {
                    polls++;
                    if (polls === 1)
                        return jsonResponse(200, {
                            id: 9,
                            state: 'running',
                            donePages: 3,
                            failedPages: 0,
                            totalPages: 11,
                            pages: [],
                            summary: null,
                        });
                    return jsonResponse(200, {
                        id: 9,
                        state: 'collected',
                        donePages: 10,
                        failedPages: 1,
                        totalPages: 11,
                        pages: [],
                        summary: {
                            rows: [
                                {
                                    artifactId: 1,
                                    page: 1,
                                    name: 'row.1',
                                    category: 'Kebaplar',
                                    product: 'Adana',
                                    priceMinorAmount: 25000,
                                    currencyCode: 'TRY',
                                    confidence: 0.95,
                                    uncertain: false,
                                },
                                {
                                    artifactId: 2,
                                    page: 2,
                                    name: 'row.1',
                                    category: 'İçecekler',
                                    product: 'Ayran',
                                    priceMinorAmount: 3000,
                                    currencyCode: 'TRY',
                                    confidence: 0.9,
                                    uncertain: false,
                                },
                            ],
                            artifactIds: [1, 2],
                            duplicatesSkipped: 9,
                            failedPages: [{ mediaAssetId: 110, reason: 'unparseable' }],
                        },
                    });
                }
                if (u.endsWith('/ai-imports/batch') && method === 'POST')
                    throw new Error('eşzamanlı yol 11 sayfada kullanılmamalı');
                return jsonResponse(200, { ok: true });
            });
            vi.stubGlobal('fetch', fetchMock);

            const { MenuCatalogWorkspace } =
                (await import('./MenuCatalogWorkspace')) as unknown as {
                    MenuCatalogWorkspace: React.ComponentType<{
                        workspaceId: number;
                        locationId: number;
                    }>;
                };
            render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);
            await screen.findByRole('heading', { name: 'Kebaplar' });
            const user = userEvent.setup();

            await user.click(
                await screen.findByRole('button', { name: 'Import from a photo (AI)' }),
            );
            for (const m of media) {
                await user.click(await screen.findByRole('checkbox', { name: m.altText }));
            }
            await user.click(screen.getByRole('button', { name: 'Read these photos' }));

            // İlk yoklama 'running' döner; ekran 2 sn bekleyip yeniden sorar.
            await waitFor(
                () => {
                    expect(
                        screen.getByText(/2 rows from 10 pages\. 9 duplicate rows were skipped\./),
                    ).toBeInTheDocument();
                },
                { timeout: 8000 },
            );
            expect(screen.getByText(/Kebaplar — Adana/)).toBeInTheDocument();
            expect(screen.getAllByText(/Sayfa 11/).length).toBeGreaterThanOrEqual(2);
            expect(
                fetchMock.mock.calls.some(([u]) => String(u).endsWith('/ai-imports/batch')),
            ).toBe(false);
        },
    );
});
