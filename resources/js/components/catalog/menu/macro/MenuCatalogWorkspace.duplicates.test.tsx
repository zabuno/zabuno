import type React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';

/**
 * OLASI TEKRARLAR — `docs/96`/`docs/97` Yolculuk C (core-taxonomy).
 *
 * Backend zaten vardı (FF-47); bu ekran onu ilk kez menü kataloğunda
 * gösteriyor. SALT OKUNUR: hiçbir aday bir düğmeye bağlanmaz.
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

function tree() {
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
                menuItems: [
                    {
                        id: 11,
                        categoryId: 5,
                        productId: 9,
                        productName: 'Adana Kebap',
                        priceMinorAmount: 38000,
                        currencyCode: 'TRY',
                        position: 1,
                        isVisible: true,
                        allergens: [],
                    },
                ],
            },
        ],
    };
}

async function renderWorkspace(duplicatesResponse: Response) {
    vi.stubGlobal(
        'fetch',
        vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();

            if (String(url) === '/sanctum/csrf-cookie') return jsonResponse(204, {});
            if (String(url).endsWith('/brand') && method === 'GET') {
                return jsonResponse(200, { id: 1, workspaceId: WORKSPACE_ID, currency: 'TRY' });
            }
            if (String(url).endsWith(`/locations/${LOCATION_ID}/menu`) && method === 'GET') {
                return jsonResponse(200, tree());
            }
            if (String(url).endsWith('/menu/duplicate-candidates') && method === 'GET') {
                return duplicatesResponse;
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

describe('olası tekrarlar (docs/97 Yolculuk C)', () => {
    it('aday varsa bölüm görünür ve çifti adlarıyla listeler', async () => {
        await renderWorkspace(
            jsonResponse(200, {
                candidates: [
                    {
                        productAId: 9,
                        productAName: 'Adana Kebap',
                        productBId: 14,
                        productBName: 'Adana Kebabı',
                        similarity: 0.94,
                    },
                ],
            }),
        );

        await waitFor(() => {
            expect(screen.getByText('Possible duplicates (1)')).toBeInTheDocument();
        });
        expect(screen.getByText('“Adana Kebap” and “Adana Kebabı”')).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('aday yoksa bölüm hiç görünmez', async () => {
        await renderWorkspace(jsonResponse(200, { candidates: [] }));

        // Yüklemenin bittiğinden emin olmak için bir an bekle, sonra
        // bölümün render edilmediğini doğrula.
        await waitFor(() => {
            expect(screen.queryByText(/Possible duplicates/)).not.toBeInTheDocument();
        });

        vi.unstubAllGlobals();
    });

    it('istek başarısız olursa sessizce boş kalır, ekranı kırmaz', async () => {
        await renderWorkspace(jsonResponse(503, { message: 'off' }));

        await waitFor(() => {
            expect(screen.queryByText(/Possible duplicates/)).not.toBeInTheDocument();
        });
        // Ana ekran hâlâ ayakta.
        expect(screen.getByRole('heading', { name: 'Kebaplar' })).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('bir eylem sunmaz — birleştir/sil düğmesi yok', async () => {
        await renderWorkspace(
            jsonResponse(200, {
                candidates: [
                    {
                        productAId: 9,
                        productAName: 'Adana Kebap',
                        productBId: 14,
                        productBName: 'Adana Kebabı',
                        similarity: 0.94,
                    },
                ],
            }),
        );

        await waitFor(() => {
            expect(screen.getByText('Possible duplicates (1)')).toBeInTheDocument();
        });

        expect(screen.queryByRole('button', { name: /merge/i })).not.toBeInTheDocument();

        vi.unstubAllGlobals();
    });
});
